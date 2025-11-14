<?php

namespace Ikuzo\SyliusMondialRelayPlugin\EventListener;

use BitBag\SyliusShippingExportPlugin\Entity\ShippingExportInterface;
use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Webmozart\Assert\Assert;
use Ikuzo\SyliusMondialRelayPlugin\MondialRelay\ApiClient;

final class MondialRelayShippingExportEventListener
{
    public function __construct(
        private RequestStack $requestStack,
        private Filesystem $filesystem,
        private ObjectManager $shippingExportManager,
        private string $shippingLabelsPath,
        private ApiClient $client
    ) {
    }

    public function exportShipment(ResourceControllerEvent $event): void
    {
        /** @var ShippingExportInterface $shippingExport */
        $shippingExport = $event->getSubject();
        Assert::isInstanceOf($shippingExport, ShippingExportInterface::class);

        $shippingGateway = $shippingExport->getShippingGateway();
        Assert::notNull($shippingGateway);

        if ('mondial_relay' !== $shippingGateway->getCode()) {
            return;
        }

        $shipment = $shippingExport->getShipment();
        $shippingAddress = $shipment->getOrder()->getShippingAddress();
        $channel = $shipment->getOrder()->getChannel();
        $channelBilling = $channel->getShopBillingData();

        $weight = 0;
        foreach ($shipment->getOrder()->getItems() as $item) {
            $weight += $item->getQuantity() * $item->getVariant()->getWeight();
        }

        try {
            $label = $this->client->createLabel([
                'Poids' => (int) $weight,
                'NDossier' => (string)$shipment->getId(),
                'NClient' => (string)$shipment->getOrder()->getNumber(),
                'Expe_Langage' => $channelBilling->getCountryCode(),
                'Expe_Ad1' => $channelBilling->getCompany(),
                'Expe_Ad3' => $channelBilling->getStreet(),
                'Expe_Ville' => $channelBilling->getCity(),
                'Expe_CP' => $channelBilling->getPostcode(),
                'Expe_Pays' => $channelBilling->getCountryCode(),
                'Expe_Tel1' => str_replace(' ', '', $channel->getContactPhoneNumber()),
                'Expe_Mail' => $channel->getContactEmail(),
                'Dest_Langage' => $shippingAddress->getCountryCode(),
                'Dest_Ad1' => $shippingAddress->getLastName().' '.$shippingAddress->getFirstName(),
                'Dest_Ad2' => $shippingAddress->getCompany(),
                'Dest_Ad3' => $shippingAddress->getStreet(),
                'Dest_Ville' => $shippingAddress->getCity(),
                'Dest_CP' => $shippingAddress->getPostcode(),
                'Dest_Pays' => $shippingAddress->getCountryCode(),
                'Dest_Tel1' => str_replace(' ', '', $shippingAddress->getPhoneNumber()),
                'Dest_Mail' => $shipment->getOrder()->getCustomer()->getEmail(),
                'COL_Rel_Pays' => "",
                'LIV_Rel_Pays' => $shippingAddress->getCountryCode(),
                'LIV_Rel' => (string)explode('---', $shipment->getPickupPointId())[1],
                'Exp_Valeur' => $shipment->getOrder()->getItemsTotal(),
                'Exp_Devise' => $shipment->getOrder()->getCurrencyCode()
            ]);
        } catch (\Throwable $th) {
            $this->requestStack->getSession()->getFlashBag()->add('error', $th->getMessage());
            return;
        }

        $shippingExport->getShipment()->setTracking($label['number']);

        $this->requestStack->getSession()->getFlashBag()->add('success', 'bitbag.ui.shipment_data_has_been_exported'); // Add success notification
        $this->saveShippingLabel($shippingExport, $label['pdfUrl'], 'pdf'); // Save label
        $this->markShipmentAsExported($shippingExport); // Mark shipment as "Exported"
    }

    public function saveShippingLabel(
        ShippingExportInterface $shippingExport,
        string $labelContent,
        string $labelExtension
    ): void {
        $labelPath = $this->shippingLabelsPath
            . '/' . $this->getFilename($shippingExport)
            . '.' . $labelExtension;

        $this->filesystem->dumpFile($labelPath, file_get_contents($labelContent));
        $shippingExport->setLabelPath($labelPath);

        $this->shippingExportManager->persist($shippingExport);
        $this->shippingExportManager->flush();
    }

    private function getFilename(ShippingExportInterface $shippingExport): string
    {
        $shipment = $shippingExport->getShipment();
        Assert::notNull($shipment);

        $order = $shipment->getOrder();
        Assert::notNull($order);

        $orderNumber = $order->getNumber();

        $shipmentId = $shipment->getId();

        return implode(
            '_',
            [
                $shipmentId,
                preg_replace('~[^A-Za-z0-9]~', '', $orderNumber),
            ]
        );
    }

    private function markShipmentAsExported(ShippingExportInterface $shippingExport): void
    {
        $shippingExport->setState(ShippingExportInterface::STATE_EXPORTED);
        $shippingExport->setExportedAt(new \DateTime());

        $this->shippingExportManager->persist($shippingExport);
        $this->shippingExportManager->flush();
    }
}
