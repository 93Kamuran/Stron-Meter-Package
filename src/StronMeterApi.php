<?php

namespace Inensus\StronMeter;

use App\Lib\IManufacturerAPI;
use App\Misc\TransactionDataContainer;
use App\Models\MainSettings;
use App\Models\Meter\Meter;
use App\Models\Meter\MeterParameter;
use App\Models\Transaction\Transaction;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Inensus\StronMeter\Exceptions\StronCreadentialsNotFoundException;
use Inensus\StronMeter\Http\Requests\StronMeterApiRequests;
use Inensus\StronMeter\Models\StronCredential;
use Inensus\StronMeter\Models\StronTransaction;

class StronMeterApi implements IManufacturerAPI
{
    protected $api;
    private $meterParameter;
    private $transaction;
    private $rootUrl = '/vending/';
    private $stronTransaction;
    private $mainSettings;
    private $credentials;
    private $stronMeterApiRequests;

    public function __construct(
        Client $httpClient,
        MeterParameter $meterParameter,
        StronTransaction $stronTransaction,
        Transaction $transaction,
        MainSettings $mainSettings,
        StronCredential $credentials,
        StronMeterApiRequests $stronMeterApiRequests
    ) {
        $this->api = $httpClient;
        $this->meterParameter = $meterParameter;
        $this->stronTransaction = $stronTransaction;
        $this->transaction = $transaction;
        $this->mainSettings = $mainSettings;
        $this->credentials = $credentials;
        $this->stronMeterApiRequests = $stronMeterApiRequests;
    }

    public function chargeMeter(TransactionDataContainer $transactionContainer): array
    {
        $meterParameter = $transactionContainer->meterParameter;
        $transactionContainer->chargedEnergy += $transactionContainer->amount
            / ($meterParameter->tariff()->first()->total_price / 100);

        Log::debug('ENERGY TO BE CHARGED float ' . (float)$transactionContainer->chargedEnergy .
            ' Manufacturer => StronMeterApi');

        if (config('app.debug')) {
            return [
                'token' => 'debug-token',
                'energy' => (float)$transactionContainer->chargedEnergy,
            ];
        } else {
            $meter = $transactionContainer->meter;
            try {
                $credentials = $this->credentials->newQuery()->firstOrFail();
            } catch (ModelNotFoundException $e) {
                throw new StronCreadentialsNotFoundException($e->getMessage());
            }
            $mainSettings = $this->mainSettings->newQuery()->first();
            $postParams = [
                "CustomerId" => strval($meterParameter->owner->id),
                "MeterId" => $meter->serial_number,
                "Price" => strval($meterParameter->tariff->total_price / 100),
                "Rate" => "1",
                "Amount" => $transactionContainer->amount,
                "AmountTmp" => $mainSettings ? $mainSettings->currency : 'USD',
                "Company" => $credentials->company_name,
                "Employee" => $credentials->username,
                "ApiToken" => $credentials->api_token
            ];
            $url = $credentials->api_url . $this->rootUrl;
            $transactionResult = $this->stronMeterApiRequests->post($url, $postParams);
            $this->associateManufacturerTransaction($transactionContainer, $transactionResult);
            $token = $transactionResult[0];
            return [
                'token' => $token,
                'energy' => $transactionContainer->chargedEnergy
            ];
        }
    }

    public function clearMeter(Meter $meter)
    {
        // TODO: Implement clearMeter() method.
    }

    public function associateManufacturerTransaction(
        TransactionDataContainer $transactionContainer,
        $transactionResult = []
    ) {
        $manufacturerTransaction = $this->stronTransaction->newQuery()->create([
            'transaction_id' => $transactionContainer->transaction->id,
        ]);
        $transaction = $this->transaction->newQuery()->whereHasMorph(
            'originalTransaction',
            '*',
        )->find($transactionContainer->transaction->id);

        switch ($transaction->original_transaction_type) {
            case 'vodacom_transaction':
                $transaction->originalVodacom()->associate($manufacturerTransaction)->save();
                break;
            case 'airtel_transaction':
                $transaction->originalAirtel()->associate($manufacturerTransaction)->save();
                break;
            case 'agent_transaction':
                $transaction->originalAgent()->associate($manufacturerTransaction)->save();
                break;
            case 'third_party_transaction':
                $transaction->originalThirdParty()->associate($manufacturerTransaction)->save();
                break;
        }
    }
}
