<p align="center">
    <a href="https://sylius.com" target="_blank">
        <img src="https://demo.sylius.com/assets/shop/img/logo.png" />
    </a>
</p>

<h1 align="center">Mondial Relay Export Plugin</h1>

<p align="center">Export Mondial Relay thought webservice with Sylius .</p>
<p align="center">/!\ Currently in alpha /!\</p>

## Quickstart

Install & configure [BitBagCommerce / SyliusShippingExportPlugin](https://github.com/BitBagCommerce/SyliusShippingExportPlugin).



```
$ composer require ikuzostudio/mondial-relay-plugin
```

Add plugin dependencies to your `config/bundles.php` file:

```php
return [
  // ...
  Ikuzo\SyliusMondialRelayPlugin\IkuzoSyliusMondialRelayPlugin::class => ['all' => true],
];
```