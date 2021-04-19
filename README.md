# Salecto_CancelOrders

This module is ment to build for canceling pending orders by provided date, along with cron job.

## Settings

- Option `Admin >> Store >> Configuration >> Salecto >> Cancel Orders`

## Known issues

- ** Orders greater or equals to provided date will mark as canceled. **

## Developer informations
- vashishtha chauhan / Salecto

### Install module
0. Run `composer config repositories.reponame vcs https://github.com/vac19/auto-cancel`
1. Run `composer require salecto2/magento2-auto-cancel`
2. Run `php bin/magento setup:upgrade`
3. Run `php bin/magento setup:di:compile`
4. Run `php bin/magento s:s:d da_DK en_US`
5. Run `php bin/magento c:c`

### Uninstall module
1. Run `composer remove salecto2/magento2-auto-cancel`
2. Run `php bin/magento setup:di:compile`
3. Run `php bin/magento s:s:d da_DK en_US`
4. Run `php bin/magento c:c`

### Additional developer notes
Config options reference IMG `https://bsscommerce.com/media/catalog/product/cache/e5770da61a0b234f7c9a590ace8a0fc4/m/a/magento-2-auto-cacel-order-extension-general-config.png`

Reference Module `https://bsscommerce.com/magento-2-auto-cancel-order-extension.html`
