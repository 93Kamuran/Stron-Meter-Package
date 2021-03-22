<?php

namespace Inensus\StronMeter\Http\Requests;

use GuzzleHttp\Client;
use Inensus\StronMeter\Exceptions\StronApiResponseException;
use Inensus\StronMeter\Helpers\ApiHelpers;
use Inensus\StronMeter\Models\StronCredential;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\GuzzleException;

class StronMeterApiRequests
{
    private $client;
    private $apiHelpers;
    private $credential;

    public function __construct(
        Client $httpClient,
        ApiHelpers $apiHelpers,
        StronCredential $credentialModel
    ) {
        $this->client = $httpClient;
        $this->apiHelpers = $apiHelpers;
        $this->credential = $credentialModel;
    }
    public function token($url, $postParams)
    {
        try {
            $request = $this->client->post(
                $url,
                [
                    'body' => json_encode($postParams),
                    'headers' => [
                        'Content-Type' => 'application/json;charset=utf-8',
                    ],
                ]
            );
            return $this->apiHelpers->checkApiResult(json_decode((string)$request->getBody(), true));
        } catch (GuzzleException $gException) {
            Log::critical(
                'Stron Meter API Authorization Failed',
                [
                    'URL :' => $url,
                    'Body :' => json_encode($postParams),
                    'message :' => $gException->getMessage()
                ]
            );
            throw new StronApiResponseException($gException->getMessage());
        }
    }

    public function post($url, $postParams)
    {
        try {
            $request = $this->client->post(
                $url,
                [
                    'body' => json_encode($postParams),
                    'headers' => [
                        'Content-Type' => 'application/json;charset=utf-8',
                    ],
                ]
            );
           return explode(",", (string)$request->getBody());
        } catch (GuzzleException $gException) {
            Log::critical(
                'Stron API Transaction Failed',
                [
                    'URL :' => $url,
                    'Body :' => json_encode($postParams),
                    'message :' => $gException->getMessage()
                ]
            );
            throw new StronApiResponseException($gException->getMessage());
        }
    }

}
