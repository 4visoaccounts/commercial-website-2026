<?php

namespace modules\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use yii\web\Response;

class LeadController extends Controller
{
    protected array|bool|int $allowAnonymous = ['submit'];

    public function actionSubmit(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        // Honeypot check — bots fill hidden fields, humans don't
        if ($request->getBodyParam('_gotcha') !== null && $request->getBodyParam('_gotcha') !== '') {
            return $this->asJson(['success' => true]);
        }

        $payload = array_filter([
            'first_name'  => $request->getBodyParam('first_name'),
            'last_name'   => $request->getBodyParam('last_name'),
            'company'     => $request->getBodyParam('company'),
            'email'       => $request->getBodyParam('email'),
            'phone'       => $request->getBodyParam('phone'),
            'description' => $request->getBodyParam('description'),
        ]);

        $apiKey  = App::env('ZOHO_API_KEY');
        $zohoUrl = 'https://www.zohoapis.eu/crm/v7/functions/create_lead_from_website/actions/execute'
                 . '?' . http_build_query(array_merge(['auth_type' => 'apikey', 'zapikey' => $apiKey], $payload));

        try {
            $client   = Craft::createGuzzleClient();
            $response = $client->post($zohoUrl, [
                'http_errors' => false,
            ]);

            $statusCode = $response->getStatusCode();
            $body       = json_decode((string) $response->getBody(), true);

            Craft::info('Zoho response [' . $statusCode . ']: ' . json_encode($body), __METHOD__);

            // Zoho returns {"code":"success",...} for successful deluge function calls
            $zohoCode = $body['code'] ?? null;

            if ($statusCode >= 200 && $statusCode < 300 && $zohoCode === 'success') {
                return $this->asJson([
                    'success' => true,
                    'zoho'    => $body,
                ]);
            }

            $zohoMessage = $body['message'] ?? ($body['details']['output'] ?? 'Submission failed, please try again.');

            Craft::warning('Zoho lead submission rejected [' . $statusCode . ']: ' . json_encode($body), __METHOD__);

            return $this->asJson([
                'success' => false,
                'error'   => $zohoMessage,
                'zoho'    => $body,
            ]);
        } catch (\Throwable $e) {
            Craft::error('Zoho lead submission failed: ' . $e->getMessage(), __METHOD__);

            return $this->asJson([
                'success' => false,
                'error'   => 'Submission failed, please try again.',
            ]);
        }
    }
}
