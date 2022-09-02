<?php

declare(strict_types=1);

namespace Ikuzo\SyliusMondialRelayPlugin\Provider;

use Ikuzo\SyliusMondialRelayPlugin\MondialRelay\ApiClient;
use Setono\SyliusPickupPointPlugin\Exception\TimeoutException;
use Setono\SyliusPickupPointPlugin\Model\PickupPointCode;
use Setono\SyliusPickupPointPlugin\Model\PickupPointInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Setono\SyliusPickupPointPlugin\Provider\Provider;
use Webmozart\Assert\Assert;

final class MondialRelayProvider extends Provider
{
    public function __construct(
        private ApiClient $client,
        private FactoryInterface $pickupPointFactory,
        
    )
    {   
    }

    public function getCode(): string
    {
        return 'mondial_relay';
    }

    public function getName(): string
    {
        return 'Mondial Relay';
    }

    public function transform(array $point): PickupPointInterface
    {
        $pickupPoint = $this->pickupPointFactory->createNew();
        Assert::isInstanceOf($pickupPoint, PickupPointInterface::class);   

        $pickupPoint->setCode(new PickupPointCode($point['id'], $this->getCode(), $point['country']));
        $pickupPoint->setName($point['name']);
        $pickupPoint->setAddress($point['address']);
        $pickupPoint->setZipCode($point['postCode']);
        $pickupPoint->setCity($point['city']);
        $pickupPoint->setCountry($point['country']);
        $pickupPoint->setLatitude((float) str_replace(',', '.', $point['lat']));
        $pickupPoint->setLongitude((float) str_replace(',', '.', $point['long']));

        return $pickupPoint;
    }

    public function findPickupPoints(OrderInterface $order): iterable
    {
        $pickupPoints = [];
        $shippingAddress = $order->getShippingAddress();
        if (null === $shippingAddress) {
            return [];
        }

        $points = $this->client->findDeliveryPoints([
            'Pays' => $shippingAddress->getCountryCode(),
            'CP' => $shippingAddress->getPostcode(),
            'Ville' => $shippingAddress->getCity()
        ]);

        foreach ($points as $point) {
            $pickupPoints[] = $this->transform($point);
        }

        return $pickupPoints;
    }

    public function findPickupPoint(PickupPointCode $code): ?PickupPointInterface
    {
        dd($code);
    }

    public function findAllPickupPoints(): iterable
    {
        return [];
    }
}