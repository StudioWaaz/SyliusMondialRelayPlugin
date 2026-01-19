<?php

namespace Ikuzo\SyliusMondialRelayPlugin\MondialRelay;

use \SoapClient;
use BitBag\SyliusShippingExportPlugin\Entity\ShippingGatewayInterface;
use BitBag\SyliusShippingExportPlugin\Repository\ShippingGatewayRepositoryInterface;

class ApiClient
{
    private SoapClient $client;
    private string $signCode;
    private string $privateKey;
    private string $labelFormat;

    public function __construct(
        private string $wsdlUrl,
        private ShippingGatewayRepositoryInterface $shippingGatewaysRepository
    )
    {
        $shippingGateway = $this->shippingGatewaysRepository->findOneByCode('mondial_relay');
        $this->client = new \SoapClient($wsdlUrl, [
            'trace' => 1,
            'encoding'=>' UTF-8'
        ]);

        if ($shippingGateway instanceof ShippingGatewayInterface) {
            $this->signCode = $shippingGateway->getConfig()['signCode'];
            $this->privateKey = $shippingGateway->getConfig()['privateKey'];
            $this->labelFormat = $shippingGateway->getConfig()['labelFormat'];
            $this->dropOffPickupId = (string)$shippingGateway->getConfig()['dropoffPickupPointId'];
        }
    }

    public function findDeliveryPoints(array $params): iterable
    {
        $params = array_merge([
            'Enseigne' => $this->signCode,
            'Pays' => "FR",
            'Ville' => "",
            'CP' => "",
            'Latitude' => "",
            'Longitude' => "",
            'Taille' => "",
            'Poids' => "",
            'Action' => "",
            'DelaiEnvoi' => "0",
            'RayonRecherche' => "20",
            'NombreResultats' => "20"
        ], $params);

        $params['Security'] = $this->_signParams($params);

        $results = $this->client->WSI4_PointRelais_Recherche($params);

        if ($results->WSI4_PointRelais_RechercheResult->STAT != 0) {
            return [];
        }

        foreach ($results->WSI4_PointRelais_RechercheResult->PointsRelais->PointRelais_Details as $point) {
            yield [
                'id' => $point->Num,
                'name' => $point->LgAdr1,
                'address' => $point->LgAdr3,
                'postCode' => $point->CP,
                'city' => $point->Ville,
                'country' => $point->Pays,
                'lat' => $point->Latitude,
                'long' => $point->Longitude
            ];
        }
    }

    public function findOneDeviveryPoint(array $params): ?array
    {
        $params = array_merge([
            'Enseigne' => $this->signCode,
            'Pays' => "FR",
            'NumPointRelais' => ""
        ], $params);

        $results = $this->client->WSI4_PointRelais_Recherche($params);

        if ($results->WSI4_PointRelais_RechercheResult->STAT != 0) {
            return null;
        }

        $point = $results->WSI4_PointRelais_RechercheResult->PointsRelais->PointRelais_Details;
        return [
            'id' => $point->Num,
            'name' => $point->LgAdr1,
            'address' => $point->LgAdr3,
            'postCode' => $point->CP,
            'city' => $point->Ville,
            'country' => $point->Pays,
            'lat' => $point->Latitude,
            'long' => $point->Longitude
        ];
    }

    public function createLabel(array $params)
    {
        $params = $this->_clean(array_merge([
            'Enseigne'      => $this->signCode,
            'ModeCol'       => "REL",
            'ModeLiv'       => "24R",
            'NDossier'      => "",
            'NClient'       => "",
            'Expe_Langage'  => "",
            'Expe_Ad1'      => "",
            'Expe_Ad2'      => "",
            'Expe_Ad3'      => "",
            'Expe_Ad4'      => "",
            'Expe_Ville'    => "",
            'Expe_CP'       => "",
            'Expe_Pays'     => "",
            'Expe_Tel1'     => "",
            'Expe_Tel2'     => "",
            'Expe_Mail'     => "",
            'Dest_Langage'  => "",
            'Dest_Ad1'      => "",
            'Dest_Ad2'      => "",
            'Dest_Ad3'      => "",
            'Dest_Ad4'      => "",
            'Dest_Ville'    => "",
            'Dest_CP'       => "",
            'Dest_Pays'     => "",
            'Dest_Tel1'     => "",
            'Dest_Tel2'     => "",
            'Dest_Mail'     => "",
            'Poids'         => "",
            'Longueur'      => "",
            'Taille'        => "",
            'NbColis'       => '1',
            'CRT_Valeur'    => '0',
            'CRT_Devise'    => "EUR",
            'Exp_Valeur'    => "0",
            'Exp_Devise'    => "EUR",
            'COL_Rel_Pays'  => "",
            'COL_Rel'       => $this->dropOffPickupId,
            'LIV_Rel_Pays'  => "",
            'LIV_Rel'       => "",
            'TAvisage'      => "",
            'TReprise'      => "N",
            'Montage'       => "0",
            'TRDV'          => "",
            'Assurance'     => "0",
            'Instructions'  => "",
            'Texte'         => "",

        ], $params));

        $params['Security'] = $this->_signParams($params);

        $result = $this->client->WSI2_CreationEtiquette($params);

        if ($result->WSI2_CreationEtiquetteResult->STAT == 0) {
            return [
                'number' => $result->WSI2_CreationEtiquetteResult->ExpeditionNum,
                'pdfUrl' => 'https://www.mondialrelay.com'.$this->_setLabelFormat($result->WSI2_CreationEtiquetteResult->URL_Etiquette)
            ];
        }

        throw new \Exception("Error while generating label request: STAT={$result->WSI2_CreationEtiquetteResult->STAT}", 1);

    }

    private function _setLabelFormat(string $url): string
    {
        return preg_replace('/&format=(\w+)&/', '&format='.$this->labelFormat.'&', $url);
    }

    private function _signParams(array $params): string
    {
        if (isset($params['Texte'])) {
            unset($params['Texte']);
        }

        if (isset($params['Security'])) {
            unset($params['Security']);
        }

        return strtoupper(md5(implode("", $params).$this->privateKey));
    }

    public function _clean(array $params): array
    {
        $unwanted_array = [
            'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'
        ];

        foreach ($params as $key => $value) {
            $params[$key] = strtr( $value, $unwanted_array);
        }

        return $params;
    }


}
