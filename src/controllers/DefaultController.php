<?php

namespace vaersaagod\muxmate\controllers;

use Craft;
use craft\elements\Asset;
use craft\web\Controller;

use vaersaagod\muxmate\helpers\MuxMateHelper;

use yii\base\InvalidConfigException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class DefaultController extends Controller
{

    /**
     * Resolves the requested video asset, ensuring the current user is allowed to save it.
     *
     * @return Asset
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     * @throws InvalidConfigException
     * @throws \yii\web\BadRequestHttpException
     */
    private function _requireVideoAsset(): Asset
    {
        $this->requireCpRequest();

        $assetId = $this->request->getRequiredBodyParam('assetId');

        if (!$asset = Craft::$app->getAssets()->getAssetById((int)$assetId)) {
            throw new NotFoundHttpException();
        }

        // Make sure the current user is actually allowed to edit this asset
        $volumeUid = $asset->getVolume()->uid;
        if (
            !Craft::$app->getUser()->checkPermission("saveAssets:$volumeUid") ||
            !Craft::$app->getUser()->checkPermission("viewAssets:$volumeUid")
        ) {
            throw new ForbiddenHttpException();
        }

        return $asset;
    }

    /**
     * @return Response|null
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     * @throws InvalidConfigException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionCreate(): ?Response
    {

        $asset = $this->_requireVideoAsset();

        if ($asset->kind !== Asset::KIND_VIDEO) {
            return $this->asFailure(message: \Craft::t('_muxmate', 'This asset is not a video.'));
        }

        if (MuxMateHelper::getMuxAssetId($asset)) {
            MuxMateHelper::deleteMuxAttributesForAsset($asset);
        }

        if (!MuxMateHelper::updateOrCreateMuxAsset($asset)) {
            return $this->asFailure(message: \Craft::t('_muxmate', 'Unable to create Mux asset.'));
        }

        return $this->asSuccess(message: \Craft::t('_muxmate', 'Mux asset created.'));

    }

    /**
     * @return Response|null
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     * @throws InvalidConfigException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionDelete(): ?Response
    {

        $asset = $this->_requireVideoAsset();

        if ($asset->kind !== Asset::KIND_VIDEO) {
            return $this->asFailure(message: \Craft::t('_muxmate', 'This asset is not a video.'));
        }

        if (!MuxMateHelper::deleteMuxAttributesForAsset($asset)) {
            return $this->asFailure(message: \Craft::t('_muxmate', 'Failed to delete Mux asset for video.'));
        }

        return $this->asSuccess(message: \Craft::t('_muxmate', 'Mux asset deleted.'));

    }

    /**
     * @return Response|null
     * @throws NotFoundHttpException
     * @throws ForbiddenHttpException
     * @throws InvalidConfigException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionUpdateData(): ?Response
    {
        $asset = $this->_requireVideoAsset();

        if ($asset->kind !== Asset::KIND_VIDEO) {
            return $this->asFailure(message: \Craft::t('_muxmate', 'This asset is not a video.'));
        }

        if (!MuxMateHelper::updateOrCreateMuxAsset($asset)) {
            return $this->asFailure(message: \Craft::t('_muxmate', 'Unable to update Mux data.'));
        }

        return $this->asSuccess(message: \Craft::t('_muxmate', 'Mux data updated.'));

    }

}
