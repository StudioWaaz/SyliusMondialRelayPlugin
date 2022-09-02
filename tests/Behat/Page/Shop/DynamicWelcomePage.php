<?php

declare(strict_types=1);

namespace Tests\Ikuzo\SyliusMondialRelayPlugin\Behat\Page\Shop;

use FriendsOfBehat\PageObjectExtension\Page\SymfonyPage;

class DynamicWelcomePage extends SymfonyPage implements WelcomePageInterface
{
    /**
     * {@inheritdoc}
     */
    public function getGreeting(): string
    {
        return $this->getSession()->getPage()->waitFor(3, function (): string {
            $greeting = $this->getElement('greeting')->getText();

            if ('Loading...' === $greeting) {
                return '';
            }

            return $greeting;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteName(): string
    {
        return 'ikuzo_sylius_mondial_relay_dynamic_welcome';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'greeting' => '#greeting',
        ]);
    }
}
