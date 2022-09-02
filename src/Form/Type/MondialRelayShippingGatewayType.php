<?php

namespace Ikuzo\SyliusMondialRelayPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class MondialRelayShippingGatewayType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('signCode', TextType::class, [
                'label' => 'ikuzo_sylius_mondial_relay.form.signCode'
            ])
            ->add('privateKey', TextType::class, [
                'label' => 'ikuzo_sylius_mondial_relay.form.privateKey'
            ])
            ->add('brandCode', TextType::class, [
                'label' => 'ikuzo_sylius_mondial_relay.form.brandCode'
            ])
            ->add('labelFormat', ChoiceType::class, [
                'label' => 'ikuzo_sylius_mondial_relay.form.labelFormat',
                'choices' => [
                    'ikuzo_sylius_mondial_relay.form.a4' => 'A4',
                    'ikuzo_sylius_mondial_relay.form.10_15' => '10x15',
                ]
            ])
            ->add('dropoffPickupPointId', TextType::class, [
                'label' => 'ikuzo_sylius_mondial_relay.form.dropoffPickupPointId',
                'required' => false
            ])
        ;
    }
}
