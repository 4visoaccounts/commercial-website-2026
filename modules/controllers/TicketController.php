<?php

namespace modules\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use yii\web\Response;
use yii\web\UploadedFile;

class TicketController extends Controller
{
    protected array|bool|int $allowAnonymous = ['submit'];

    public function actionSubmit(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        if ($request->getBodyParam('_gotcha') !== null && $request->getBodyParam('_gotcha') !== '') {
            return $this->asJson(['success' => true]);
        }

        $name        = trim($request->getBodyParam('name') ?? '');
        $email       = trim($request->getBodyParam('email') ?? '');
        $description = trim($request->getBodyParam('description') ?? '');

        if (!$name || !$email || !$description) {
            return $this->asJson(['success' => false, 'error' => 'All fields are required.']);
        }

        $attachment = UploadedFile::getInstanceByName('attachment');

        try {
            $token = $this->getAccessToken();

            $contactId = $this->createContact($token, $name, $email);
            $ticket    = $this->postTicket($token, $contactId, $name, $description);

            $statusCode = $ticket->getStatusCode();
            $body       = json_decode((string) $ticket->getBody(), true);

            // Token expired — refresh and retry
            if ($statusCode === 401) {
                Craft::info('Zoho Desk token expired, refreshing.', __METHOD__);
                $token     = $this->refreshAccessToken();
                $contactId = $this->createContact($token, $name, $email);
                $ticket    = $this->postTicket($token, $contactId, $name, $description);

                $statusCode = $ticket->getStatusCode();
                $body       = json_decode((string) $ticket->getBody(), true);
            }

            Craft::info('Zoho Desk ticket response [' . $statusCode . ']: ' . json_encode($body), __METHOD__);

            if ($statusCode < 200 || $statusCode >= 300) {
                $errorMessage = $body['message'] ?? 'Submission failed, please try again.';
                Craft::warning('Zoho Desk ticket rejected [' . $statusCode . ']: ' . json_encode($body), __METHOD__);
                return $this->asJson(['success' => false, 'error' => $errorMessage]);
            }

            $ticketId = $body['id'] ?? null;

            if ($ticketId && $attachment && $attachment->tempName) {
                $this->uploadAttachment($token, $ticketId, $attachment);
            }

            return $this->asJson(['success' => true, 'ticket' => $body]);
        } catch (\Throwable $e) {
            Craft::error('Zoho Desk ticket submission failed: ' . $e->getMessage(), __METHOD__);

            return $this->asJson(['success' => false, 'error' => 'Submission failed, please try again.']);
        }
    }

    private function createContact(string $token, string $name, string $email): string
    {
        $parts     = explode(' ', $name, 2);
        $firstName = count($parts) > 1 ? $parts[0] : null;
        $lastName  = count($parts) > 1 ? $parts[1] : $parts[0];

        $response = Craft::createGuzzleClient()->post('https://desk.zoho.eu/api/v1/contacts', [
            'http_errors' => false,
            'headers'     => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'json' => array_filter([
                'firstName' => $firstName,
                'lastName'  => $lastName,
                'email'     => $email,
            ]),
        ]);

        $body = json_decode((string) $response->getBody(), true);

        if (empty($body['id'])) {
            throw new \RuntimeException('Failed to create Zoho Desk contact: ' . json_encode($body));
        }

        return $body['id'];
    }

    private function postTicket(string $token, string $contactId, string $subject, string $description): \Psr\Http\Message\ResponseInterface
    {
        $departmentId = App::env('ZOHO_DESK_DEPARTMENT_ID');

        return Craft::createGuzzleClient()->post('https://desk.zoho.eu/api/v1/tickets', [
            'http_errors' => false,
            'headers'     => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'json' => array_filter([
                'subject'      => $subject,
                'description'  => $description,
                'contactId'    => $contactId,
                'departmentId' => $departmentId ?: null,
            ]),
        ]);
    }

    private function uploadAttachment(string $token, string $ticketId, UploadedFile $file): void
    {
        $response = Craft::createGuzzleClient()->post(
            "https://desk.zoho.eu/api/v1/tickets/{$ticketId}/attachments",
            [
                'http_errors' => false,
                'headers'     => [
                    'Authorization' => 'Zoho-oauthtoken ' . $token,
                    'orgId'         => App::env('ZOHO_DESK_ORG_ID'),
                ],
                'multipart' => [
                    [
                        'name'     => 'file',
                        'contents' => fopen($file->tempName, 'r'),
                        'filename' => $file->name,
                    ],
                ],
            ]
        );

        $statusCode = $response->getStatusCode();
        $body       = json_decode((string) $response->getBody(), true);

        if ($statusCode >= 200 && $statusCode < 300) {
            Craft::info('Zoho Desk attachment uploaded for ticket ' . $ticketId, __METHOD__);
        } else {
            // Log but don't fail the whole request — ticket was created successfully
            Craft::warning('Zoho Desk attachment upload failed [' . $statusCode . ']: ' . json_encode($body), __METHOD__);
        }
    }

    private function getAccessToken(): string
    {
        $cached = Craft::$app->getCache()->get('zoho_desk_access_token');

        if ($cached) {
            return $cached;
        }

        $envToken = App::env('ZOHO_DESK_ACCESS_TOKEN');

        if ($envToken) {
            Craft::$app->getCache()->set('zoho_desk_access_token', $envToken, 3480);
            return $envToken;
        }

        return $this->refreshAccessToken();
    }

    private function refreshAccessToken(): string
    {
        $client   = Craft::createGuzzleClient();
        $response = $client->post('https://accounts.zoho.eu/oauth/v2/token', [
            'http_errors' => false,
            'form_params' => [
                'grant_type'    => 'refresh_token',
                'client_id'     => App::env('ZOHO_DESK_CLIENT_ID'),
                'client_secret' => App::env('ZOHO_DESK_CLIENT_SECRET'),
                'refresh_token' => App::env('ZOHO_DESK_REFRESH_TOKEN'),
            ],
        ]);

        $body        = json_decode((string) $response->getBody(), true);
        $accessToken = $body['access_token'] ?? null;

        if (!$accessToken) {
            Craft::error('Zoho Desk token refresh failed: ' . json_encode($body), __METHOD__);
            throw new \RuntimeException('Failed to refresh Zoho Desk access token.');
        }

        Craft::info('Zoho Desk access token refreshed.', __METHOD__);
        Craft::$app->getCache()->set('zoho_desk_access_token', $accessToken, 3480);

        return $accessToken;
    }
}
