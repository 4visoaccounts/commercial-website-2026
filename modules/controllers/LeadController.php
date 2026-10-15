<?php

namespace modules\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use Psr\Http\Message\ResponseInterface;
use yii\web\Response;

/**
 * Creates Leads in Zoho CRM via the REST API (OAuth).
 *
 * Env vars:
 *   ZOHO_CRM_CLIENT_ID
 *   ZOHO_CRM_CLIENT_SECRET
 *   ZOHO_CRM_REFRESH_TOKEN
 *   ZOHO_CRM_ACCESS_TOKEN   (optional seed; refreshed automatically when expired)
 */
class LeadController extends Controller
{
    protected array|bool|int $allowAnonymous = ['submit', 'waitlist'];

    private const LEADS_URL   = 'https://www.zohoapis.eu/crm/v7/Leads';
    private const TOKEN_URL   = 'https://accounts.zoho.eu/oauth/v2/token';
    private const CACHE_KEY   = 'zoho_crm_access_token';
    private const CACHE_TTL   = 3480; // Zoho tokens live 3600s; refresh a bit early
    private const LEAD_SOURCE = '4Viso Website';
    private const PLACEHOLDER = '--To be filled in--';

    public function actionSubmit(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        // Honeypot check — bots fill hidden fields, humans don't
        if ($request->getBodyParam('_gotcha') !== null && $request->getBodyParam('_gotcha') !== '') {
            return $this->asJson(['success' => true]);
        }

        $description = trim((string) $request->getBodyParam('description'));

        $lead = [
            'First_Name'  => trim((string) $request->getBodyParam('first_name')) ?: null,
            'Last_Name'   => trim((string) $request->getBodyParam('last_name')) ?: self::PLACEHOLDER,
            'Company'     => trim((string) $request->getBodyParam('company')) ?: self::PLACEHOLDER,
            'Email'       => trim((string) $request->getBodyParam('email')) ?: null,
            'Phone'       => trim((string) $request->getBodyParam('phone')) ?: null,
            'Description' => $description !== '' ? 'contact form: ' . $description : null,
        ];

        return $this->createLead($lead, 'lead');
    }

    public function actionWaitlist(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        // Honeypot check — bots fill hidden fields, humans don't
        if ($request->getBodyParam('_gotcha') !== null && $request->getBodyParam('_gotcha') !== '') {
            return $this->asJson(['success' => true]);
        }

        $lead = [
            'First_Name'  => self::PLACEHOLDER,
            'Last_Name'   => self::PLACEHOLDER,
            'Company'     => self::PLACEHOLDER,
            'Email'       => trim((string) $request->getBodyParam('email')) ?: null,
            'Description' => 'waitlist',
        ];

        return $this->createLead($lead, 'waitlist');
    }

    // ------------------------------------------------------------------
    // Zoho CRM
    // ------------------------------------------------------------------

    private function createLead(array $lead, string $context): Response
    {
        $lead['Lead_Source'] = self::LEAD_SOURCE;
        $lead = array_filter($lead, static fn($v) => $v !== null && $v !== '');

        try {
            $token    = $this->getAccessToken();
            $response = $this->postLead($token, $lead);

            $statusCode = $response->getStatusCode();
            $body       = json_decode((string) $response->getBody(), true);

            // Token expired/invalid — refresh and retry once
            if ($statusCode === 401) {
                Craft::info('Zoho CRM token expired, refreshing.', __METHOD__);
                $token    = $this->refreshAccessToken();
                $response = $this->postLead($token, $lead);

                $statusCode = $response->getStatusCode();
                $body       = json_decode((string) $response->getBody(), true);
            }

            Craft::info('Zoho CRM ' . $context . ' response [' . $statusCode . ']: ' . json_encode($body), __METHOD__);

            // Zoho returns {"data":[{"code":"SUCCESS","status":"success","details":{"id":...}}]} with HTTP 201
            $record = $body['data'][0] ?? [];
            $code   = $record['code'] ?? null;

            if ($statusCode >= 200 && $statusCode < 300 && $code === 'SUCCESS') {
                return $this->asJson([
                    'success' => true,
                    'zoho'    => $record,
                ]);
            }

            $zohoMessage = $record['message'] ?? ($body['message'] ?? 'Submission failed, please try again.');

            Craft::warning('Zoho CRM ' . $context . ' rejected [' . $statusCode . ']: ' . json_encode($body), __METHOD__);

            return $this->asJson([
                'success' => false,
                'error'   => $zohoMessage,
                'zoho'    => $body,
            ]);
        } catch (\Throwable $e) {
            Craft::error('Zoho CRM ' . $context . ' failed: ' . $e->getMessage(), __METHOD__);

            return $this->asJson([
                'success' => false,
                'error'   => 'Submission failed, please try again.',
            ]);
        }
    }

    private function postLead(string $token, array $lead): ResponseInterface
    {
        return Craft::createGuzzleClient()->post(self::LEADS_URL, [
            'http_errors' => false,
            'headers'     => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'data' => [$lead],
            ],
        ]);
    }

    private function getAccessToken(): string
    {
        $cache  = Craft::$app->getCache();
        $cached = $cache->get(self::CACHE_KEY);

        if ($cached) {
            return $cached;
        }

        $envToken = App::env('ZOHO_CRM_ACCESS_TOKEN');

        if ($envToken) {
            $cache->set(self::CACHE_KEY, $envToken, self::CACHE_TTL);
            return $envToken;
        }

        return $this->refreshAccessToken();
    }

    private function refreshAccessToken(): string
    {
        $response = Craft::createGuzzleClient()->post(self::TOKEN_URL, [
            'http_errors' => false,
            'form_params' => [
                'grant_type'    => 'refresh_token',
                'client_id'     => App::env('ZOHO_CRM_CLIENT_ID'),
                'client_secret' => App::env('ZOHO_CRM_CLIENT_SECRET'),
                'refresh_token' => App::env('ZOHO_CRM_REFRESH_TOKEN'),
            ],
        ]);

        $body        = json_decode((string) $response->getBody(), true);
        $accessToken = $body['access_token'] ?? null;

        if (!$accessToken) {
            Craft::error('Zoho CRM token refresh failed: ' . json_encode($body), __METHOD__);
            throw new \RuntimeException('Failed to refresh Zoho CRM access token.');
        }

        Craft::info('Zoho CRM access token refreshed.', __METHOD__);
        Craft::$app->getCache()->set(self::CACHE_KEY, $accessToken, self::CACHE_TTL);

        return $accessToken;
    }
}
