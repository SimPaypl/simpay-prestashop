# Simpay Prestashop

### Requirements

Prestashop >= 8.0 
PHP >= 8.1

### Installation

1. Go to release page and download latest package version (eg. simpay-prestashop-v0.1.0.zip)
2. Extract package contents into your modules directory (eg. ```/var/www/html/modules```). Path to simpay.php should according to example should be be following - ```/var/www/html/modules/simpay/simpay.php```
3. Install Simpay plugin (Prestashop Admin Panel -> Modules -> Module Manager -> Simpay -> Install)
4. Configure Simpay plugin (Prestashop Admin Panel -> Modules -> Module Manager -> Simpay -> Configure)


### Development

```bash
docker compose up -d
```

Shop link - http://localhost:28080
Admin panel link - http://localhost:28080/admin

```yaml
email: demo@simpay.pl
password: simpay_demo
```
