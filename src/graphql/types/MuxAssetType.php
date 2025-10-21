<?php

namespace vaersaagod\muxmate\graphql\types;

use vaersaagod\muxmate\MuxMate;
use vaersaagod\muxmate\models\MuxMateFieldAttributes;
use vaersaagod\muxmate\models\MuxPlaybackId;

use Illuminate\Support\Collection;

use craft\gql\base\ObjectType;
use GraphQL\Type\Definition\Type;

class MuxAssetType extends ObjectType
{
    public $name = 'MuxMate';
    public function __construct()
    {
        $config = [
            'name' => $this->name,
            'fields' => [
                'assetId' => [
                    'name' => 'assetId',
                    'type' => Type::string(),
                    'description' => 'The Mux asset ID for this video asset.',
                    'resolve' => function (MuxMateFieldAttributes $value) {
                        if (!isset($value->muxAssetId)) {
                            return null;
                        }

                        return strval($value->muxAssetId);
                    },
                ],
                'playbackId' => [
                    'name' => 'playbackId',
                    'type' => Type::string(),
                    'description' => 'The Mux playback ID for this video asset with the default policy.',
                    'resolve' => function (MuxMateFieldAttributes $value) {
                        $policy = $policy ?? MuxMate::getInstance()->getSettings()->defaultPolicy;
                        $playbackId = Collection::make($value->muxMetaData['playback_ids'] ?? [])
                            ->where('policy', $policy)
                            ->first();

                        if (!$playbackId) {
                            return null;
                        }

                        return new MuxPlaybackId($playbackId);
                    },
                ],
                'aspectRatio' => [
                    'name' => 'aspectRatio',
                    'type' => Type::string(),
                    'description' => 'The aspect ratio for this video asset. ex "16:9".',
                    'resolve' => function (MuxMateFieldAttributes $value) {
                        if (!isset($value->muxMetaData['aspect_ratio'])) {
                            return null;
                        }

                        return strval($value->muxMetaData['aspect_ratio']);
                    },
                ],
            ],
        ];


        parent::__construct($config);
    }
}
