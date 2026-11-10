# TradeTracker for Magento® 2

The TradeTracker Connect extension makes it effortless to connect your Magento® 2 catalog with the TradeTracker platform.

## Installation
To make the integration process as easy as possible for you, we have developed various plugins for your webshop software package. 
This is the manual for installing the Magento® 2 Plugin.
Before you start up the installation process, we recommend that you make a backup of your webshop files, as well as the database.

There are 2 different methods to install the Magento® 2 extension.
1.	Install by using Composer 
2.	Install by using the Magento® Marketplace (coming soon)
----
### Installation using Composer ###
Magento® 2 use the Composer to manage the module package and the library. Composer is a dependency manager for PHP. Composer declare the libraries your project depends on and it will manage (install/update) them for you.

Check if your server has composer installed by running the following command:
```
composer –v
``` 
If your server doesn’t have composer installed, you can easily install it by using this manual: https://getcomposer.org/doc/00-intro.md

Step-by-step to install the Magento® 2 extension through Composer:

1.	Connect to your server running Magento® 2 using SSH or other method (make sure you have access to the command line).
2.	Locate your Magento® 2 project root.
3.	Install the Magento® 2 extension through composer and wait till it's completed:
```
composer require tradetracker-com/magento2
``` 
4.	Once completed run the Magento® module enable command:
```
bin/magento module:enable TradeTracker_Connect
``` 
5.	After that run the Magento® upgrade and clean the caches:
```
php bin/magento setup:upgrade
php bin/magento cache:flush
```
6.  If Magento® is running in production mode you also need to redeploy the static content:
```
php bin/magento setup:static-content:deploy
```
7.  After the installation: Go to your Magento® admin portal and open _Stores_ > _Configuration_ > _Tradetracker_.
----
### Installation using the Magento® Marketplace ###
Available Soon

## Compatibility
The module requires PHP 8.2 or higher and is tested on Magento 2.4.7 through 2.4.9 with PHP 8.2, 8.3, 8.4 and 8.5.

PHP 8.1 and older are not supported: these versions no longer receive security fixes from the PHP project. Run a PHP build that includes the October 2026 security fixes (8.2.35, 8.3.36, 8.4.27, 8.5.12 or later). Merchants on an older PHP version can keep using release 1.8.0 but are strongly advised to update PHP first.

## Credentials storage
The Customer ID and Passphrase are entered under _Stores_ > _Configuration_ > _TradeTracker_ > _General_ and can be set per website or store view. Magento stores them in the `core_config_data` table under `tradetracker/general/customer_id` and `tradetracker/general/passphrase`. The passphrase is stored encrypted with the Magento encryption key; existing plain-text values are encrypted automatically during `bin/magento setup:upgrade`.

## CLI
The product feed can be generated from the command line with `bin/magento tradetracker:feed:create`. See [docs/CLI_COMMANDS.md](docs/CLI_COMMANDS.md) for the options.
