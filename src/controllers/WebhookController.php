<?php

namespace vaersaagod\muxmate\controllers;

use Craft;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\Json;
use craft\web\Controller;

use vaersaagod\muxmate\fields\MuxMateField;
use vaersaagod\muxmate\helpers\MuxMateHelper;
use vaersaagod\muxmate\MuxMate;

use yii\web\BadRequestHttpException;

class WebhookController extends Controller
{

    public array|bool|int $allowAnonymous = true;

    public $enableCsrfValidation = false;

    /** @var int Maximum age (in seconds) of a webhook, based on its signature timestamp, before it's rejected as a replay */
    private const SIGNATURE_TOLERANCE = 300;

    /**
     * @return bool
     * @throws BadRequestHttpException
     */
    public function actionIndex(): bool
    {

        $this->requirePostRequest();

        $webhookJson = $this->request->getRawBody();

        if (empty($webhookJson)) {
            throw new BadRequestHttpException();
        }

        // Verify the Mux signature before trusting anything in the payload
        if (!$this->_verifySignature($webhookJson)) {
            throw new BadRequestHttpException('Invalid webhook signature.');
        }

        $webhookData = Json::decode($webhookJson);
        $event = $webhookData['type'] ?? null;

        Craft::info("MuxMate webhook triggered. Event type is \"$event\"", __METHOD__);

        // Check if the event is something we support
        if (!in_array($event, [
            'video.asset.created',
            'video.asset.ready',
            'video.asset.updated',
            'video.asset.static_renditions.ready',
            'video.asset.deleted',
        ])) {
            return true;
        }

        $muxAssetId = $webhookData['object']['id'] ?? null;

        if (!$muxAssetId) {
            Craft::info("Webhook failed, no Mux asset ID found in payload for event \"$event\"", __METHOD__);
            return false;
        }

        // Get the asset
        // This is a bit awkward, because we have to go via the MuxMate fields (we can't know which field to query on, in cases where there are multiple volumes w/ multiple different MuxMate fields in their layouts
        $asset = null;
        $muxMateFields = Craft::$app->getFields()->getFieldsByType(MuxMateField::class, 'global');
        foreach ($muxMateFields as $muxMateField) {
            $muxMateFieldHandle = $muxMateField->handle;
            $asset = Asset::find()
                ->kind(Asset::KIND_VIDEO)
                ->$muxMateFieldHandle([
                    'id' => $muxAssetId,
                ])
                ->one();
            if ($asset) {
                break;
            }
        }

        if (!$asset) {
            Craft::error("Webhook \"$event\" failed – no asset found for the Mux asset ID \"$muxAssetId\"", __METHOD__);
            return false;
        }

        Craft::info("Found asset \"$asset->id\" for Mux asset ID \"$muxAssetId\"", __METHOD__);

        $success = false;

        switch ($event) {
            case 'video.asset.created':
            case 'video.asset.updated':
            case 'video.asset.ready':
            case 'video.asset.static_renditions.ready':
                $muxAssetData = $webhookData['data'] ?? null;
                $success = MuxMateHelper::saveMuxAttributesToAsset($asset, [
                    'muxAssetId' => $muxAssetId,
                    'muxMetaData' => $muxAssetData,
                ]);
                if (!$success) {
                    Craft::error("Failed to save Mux attributes for asset \"$asset->id\" via webhook.", __METHOD__);
                }
                break;
            case 'video.asset.deleted':
                $success = MuxMateHelper::deleteMuxAttributesForAsset($asset, false);
                if (!$success) {
                    Craft::error("Failed to delete Mux attributes for asset \"$asset->id\" via webhook.", __METHOD__);
                }
                break;
        }

        return $success;

    }

    /**
     * Verifies the `Mux-Signature` header against the raw request body, using the
     * configured webhook signing secret. See https://docs.mux.com/guides/verify-webhook-signatures
     *
     * @param string $payload The raw request body
     * @return bool
     */
    private function _verifySignature(string $payload): bool
    {

        $secret = App::parseEnv(MuxMate::getInstance()->getSettings()->muxWebhookSigningSecret);

        if (empty($secret)) {
            Craft::error('Unable to verify Mux webhook signature: no webhook signing secret is configured.', __METHOD__);
            return false;
        }

        $header = $this->request->getHeaders()->get('Mux-Signature');
        if (empty($header)) {
            Craft::error('Mux webhook rejected: missing "Mux-Signature" header.', __METHOD__);
            return false;
        }

        // The header looks like "t=1565125137,v1=1b2c3d..."
        $parts = [];
        foreach (explode(',', $header) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) === 2) {
                $parts[$kv[0]] = $kv[1];
            }
        }

        $timestamp = $parts['t'] ?? null;
        $signature = $parts['v1'] ?? null;

        if (empty($timestamp) || empty($signature) || !ctype_digit((string)$timestamp)) {
            Craft::error('Mux webhook rejected: malformed "Mux-Signature" header.', __METHOD__);
            return false;
        }

        // Guard against replays of old (or future-dated) payloads
        if (abs(time() - (int)$timestamp) > self::SIGNATURE_TOLERANCE) {
            Craft::error('Mux webhook rejected: signature timestamp outside of tolerance.', __METHOD__);
            return false;
        }

        $expectedSignature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            Craft::error('Mux webhook rejected: signature mismatch.', __METHOD__);
            return false;
        }

        return true;
    }
}
