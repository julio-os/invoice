A custom invoicing module for [julio.com](https://julio.com) (Magento 2).  

## How to install
```posh             
rm -rf composer.lock
composer clear-cache
composer require julio-os/invoice:*
bin/magento setup:upgrade
bin/magento cache:enable
rm -rf var/di var/generation generated/code
bin/magento setup:di:compile
rm -rf pub/static/*
bin/magento setup:static-content:deploy \
	--area adminhtml \
	--theme Magento/backend \
	-f en_US es_MX
bin/magento setup:static-content:deploy \
	--area frontend \
	--theme Mgs/ninth \
	-f es_MX
bin/magento maintenance:disable
```      

## How to upgrade
```posh              
bin/magento maintenance:enable
composer remove julio-os/invoice
rm -rf composer.lock
composer clear-cache
composer require julio-os/invoice:*
bin/magento setup:upgrade
bin/magento cache:enable
rm -rf var/di var/generation generated/code
bin/magento setup:di:compile
rm -rf pub/static/*
bin/magento setup:static-content:deploy \
	--area adminhtml \
	--theme Magento/backend \
	-f en_US es_MX
bin/magento setup:static-content:deploy \
	--area frontend \
	--theme Mgs/ninth \
	-f es_MX
bin/magento maintenance:disable 
```