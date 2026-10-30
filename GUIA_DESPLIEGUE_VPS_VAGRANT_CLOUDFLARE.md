# Desplegar el proyecto como un VPS local con Vagrant y Cloudflare Tunnel

Esta guía convierte tu PC en un entorno parecido a un VPS utilizando:

- Vagrant
- VirtualBox
- Ubuntu Server dentro de una máquina virtual
- SSH
- Apache
- PHP
- Composer
- MySQL
- Cloudflare Tunnel

La ventaja de Cloudflare Tunnel es que permite publicar el dominio sin necesitar una IP pública. La conexión se inicia desde la VM hacia Cloudflare, por lo que no es necesario abrir puertos en Starlink.

## Arquitectura final

```text
Internet
   |
   v
Cloudflare Tunnel
   |
   v
PC con Starlink
   |
   v
VM Vagrant / Ubuntu
   |
   +-- Apache
   +-- PHP
   +-- MySQL
   +-- Proyecto GestorProductos
```

## 1. Requisitos en Windows

Instala:

- VirtualBox
- Vagrant
- Git
- Cuenta de Cloudflare
- Dominio administrado por Cloudflare

Comprueba las instalaciones:

```powershell
vagrant --version
VBoxManage --version
git --version
```

## 2. Crear la carpeta de la VM

```powershell
mkdir C:\Users\andre\Desktop\vps-gestor-productos
cd C:\Users\andre\Desktop\vps-gestor-productos
vagrant init ubuntu/jammy64
```

## 3. Configurar Vagrant

Reemplaza el contenido de `Vagrantfile` por:

```ruby
Vagrant.configure("2") do |config|
  config.vm.box = "ubuntu/jammy64"
  config.vm.hostname = "gestor-productos-vps"

  # SSH desde Windows:
  # ssh vagrant@127.0.0.1 -p 2222
  config.vm.network "forwarded_port",
    guest: 22,
    host: 2222,
    host_ip: "127.0.0.1"

  # Prueba local del sitio desde Windows:
  # http://localhost:8080
  config.vm.network "forwarded_port",
    guest: 80,
    host: 8080,
    host_ip: "127.0.0.1"

  # Red privada opcional para acceder a la VM desde tu PC.
  config.vm.network "private_network",
    ip: "192.168.56.10"

  config.vm.provider "virtualbox" do |vb|
    vb.name = "gestor-productos-vps"
    vb.memory = 2048
    vb.cpus = 2
  end

  config.vm.provision "shell", inline: <<-SHELL
    apt-get update
    apt-get install -y openssh-server curl git unzip apache2
  SHELL
end
```

## 4. Crear y levantar la VM

```powershell
vagrant up
```

Conectarse por SSH:

```powershell
vagrant ssh
```

También puedes conectarte directamente:

```powershell
ssh vagrant@127.0.0.1 -p 2222
```

Usuario:

```text
vagrant
```

## 5. Instalar Apache, PHP y Composer

Dentro de la VM:

```bash
sudo apt update

sudo apt install -y \
  apache2 \
  php \
  libapache2-mod-php \
  php-cli \
  php-mysql \
  php-xml \
  php-mbstring \
  php-curl \
  php-zip \
  unzip \
  git \
  composer
```

Verifica las versiones:

```bash
php -v
composer --version
apache2 -v
```

## 6. Instalar y configurar MySQL

```bash
sudo apt install -y mysql-server
sudo systemctl enable --now mysql
```

Entrar a MySQL:

```bash
sudo mysql
```

Crear la base de datos y el usuario de la aplicación:

```sql
CREATE DATABASE gestion_productos
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

CREATE USER 'gestor_app'@'localhost'
IDENTIFIED BY 'gestor_pass';

GRANT ALL PRIVILEGES
ON gestion_productos.*
TO 'gestor_app'@'localhost';

FLUSH PRIVILEGES;
EXIT;
```

No es necesario publicar MySQL hacia Internet. PHP y MySQL estarán en la misma VM.

## 7. Copiar el proyecto a la VM

### Opción A: usando Git

Dentro de la VM:

```bash
sudo mkdir -p /var/www
cd /var/www
sudo git clone URL_DE_TU_REPOSITORIO gestor-productos
```

### Opción B: copiar desde Windows

Desde PowerShell en Windows:

```powershell
scp -P 2222 -r C:\Users\andre\Desktop\Cursos\Mio\GestorProductos\* `
  vagrant@127.0.0.1:/tmp/gestor-productos
```

Dentro de la VM:

```bash
sudo mkdir -p /var/www/gestor-productos
sudo cp -r /tmp/gestor-productos/* /var/www/gestor-productos/
```

## 8. Instalar dependencias PHP

Dentro de la VM:

```bash
cd /var/www/gestor-productos
composer install --no-dev --optimize-autoloader
```

## 9. Configurar el archivo `.env`

Dentro de la VM:

```bash
cd /var/www/gestor-productos
nano .env
```

Contenido recomendado:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=gestion_productos
DB_USER=gestor_app
DB_PASSWORD=gestor_pass

JWT_SECRET=REEMPLAZAR_POR_UNA_CLAVE_LARGA
JWT_EXPIRE_SECONDS=3600
APP_ENV=production
```

Generar una clave JWT segura:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

En producción, la cookie JWT podrá utilizar `secure=true` porque Cloudflare entregará el sitio mediante HTTPS.

## 10. Ejecutar las migraciones

Desde la carpeta del proyecto:

```bash
cd /var/www/gestor-productos
composer migration:status
composer migration:migrate
```

Verificar las tablas:

```bash
sudo mysql gestion_productos -e "SHOW TABLES;"
```

Debería aparecer la tabla `users`.

## 11. Configurar Apache

Crear el VirtualHost:

```bash
sudo nano /etc/apache2/sites-available/gestor-productos.conf
```

Contenido:

```apache
<VirtualHost *:80>
    ServerName product-manager.lumina-events.com

    DocumentRoot /var/www/product_manager/public

    <Directory /var/www/product_manager/public>
        AllowOverride All
        Require all granted
        Options FollowSymLinks
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/gestor-productos-error.log
    CustomLog ${APACHE_LOG_DIR}/gestor-productos-access.log combined
</VirtualHost>
```

Activar Apache y el sitio:

```bash
sudo a2enmod rewrite
sudo a2ensite gestor-productos.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

## 12. Probar la aplicación localmente

Desde Windows abre:

```text
http://localhost:8080
```

Desde la VM:

```bash
curl -I http://localhost
```

Antes de configurar Cloudflare, el sitio debe funcionar localmente.

## 13. Configurar el dominio en Cloudflare

1. Entra a Cloudflare.
2. Agrega tu dominio.
3. Cambia los nameservers del registrador por los nameservers de Cloudflare.
4. Ve a **Networking > Tunnels**.
5. Selecciona **Create Tunnel**.
6. Usa un nombre como:

```text
gestor-productos-vps
```

7. Selecciona Linux.
8. Copia el token de instalación.

Cloudflare Tunnel necesita que el dominio esté agregado a Cloudflare para publicar el hostname.

## 14. Instalar Cloudflare Tunnel en la VM

Instala `cloudflared` siguiendo el paquete oficial para Ubuntu/Debian.

Luego instala el servicio usando el token generado en Cloudflare:

```bash
sudo cloudflared service install TU_TOKEN_DEL_TUNNEL
```

Configura el servicio interno como:

```text
http://localhost:80
```

Configura el hostname público:

```text
gestor.tudominio.com
```

También puedes asociar el DNS mediante:

```bash
cloudflared tunnel route dns NOMBRE_DEL_TUNNEL gestor.tudominio.com
```

Verificar el servicio:

```bash
sudo systemctl status cloudflared
```

Ver logs:

```bash
sudo journalctl -u cloudflared -f
```

## 15. Resultado final

La aplicación estará disponible en:

```text
https://product-manager.lumina-events.com
```

Flujo:

```text
Usuario
  -> HTTPS
Cloudflare
  -> Tunnel saliente
cloudflared dentro de la VM
  -> localhost:80
Apache
  -> /var/www/gestor-productos/public
PHP + Doctrine + MySQL
```

## 16. Seguridad

- No publicar el puerto `3306`.
- No publicar PhpMyAdmin en Internet.
- No usar `root` desde PHP.
- Usar un usuario MySQL exclusivo para la aplicación.
- Usar HTTPS mediante Cloudflare.
- Mantener `JWT_SECRET` fuera de Git.
- Usar `secure=true` en la cookie cuando `APP_ENV=production`.
- Mantener Apache y Ubuntu actualizados.
- No exponer SSH directamente a Internet.

## 17. Orden recomendado

```text
1. Instalar VirtualBox y Vagrant.
2. Crear la VM.
3. Conectarse por SSH.
4. Instalar Apache, PHP, Composer y MySQL.
5. Copiar el proyecto.
6. Configurar .env.
7. Ejecutar migraciones.
8. Configurar Apache.
9. Probar http://localhost:8080.
10. Configurar Cloudflare Tunnel.
11. Asociar el dominio.
12. Probar https://product-manager.lumina-events.com.
```

## Configuración Cloudflare utilizada en este proyecto

La configuración definitiva no utiliza el onboarding de Zero Trust porque solicitaba un método de pago. Se utilizó un túnel administrado localmente desde Ubuntu.

### Instalar `cloudflared`

```bash
sudo mkdir -p --mode=0755 /usr/share/keyrings
curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg \
  | sudo tee /usr/share/keyrings/cloudflare-main.gpg >/dev/null

echo "deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main" \
  | sudo tee /etc/apt/sources.list.d/cloudflared.list

sudo apt-get update
sudo apt-get install -y cloudflared
```

### Copiar el certificado desde Windows

Si `cloudflared tunnel login` se ejecutó en Windows, copia el certificado a la VM desde la carpeta donde está el `Vagrantfile`:

```powershell
vagrant upload "C:\Users\andre\Downloads\cert.pem" /tmp/cert.pem
```

Dentro de Ubuntu:

```bash
sudo mkdir -p /root/.cloudflared
sudo mv /tmp/cert.pem /root/.cloudflared/cert.pem
sudo chmod 600 /root/.cloudflared/cert.pem
```

### Crear el túnel y el DNS

```bash
cloudflared tunnel create lumina-events-vps
cloudflared tunnel route dns lumina-events-vps product-manager.lumina-events.com
```

El túnel creado para este proyecto tiene el ID:

```text
f9f9cd2c-703e-4971-97dd-8f70db1eeb6d
```

### Configurar el túnel

Crear `/etc/cloudflared/config.yml`:

```yaml
tunnel: f9f9cd2c-703e-4971-97dd-8f70db1eeb6d
credentials-file: /root/.cloudflared/f9f9cd2c-703e-4971-97dd-8f70db1eeb6d.json

ingress:
  - hostname: product-manager.lumina-events.com
    service: http://localhost:80

  - service: http_status:404
```

### Probar y activar como servicio

Prueba primero en primer plano:

```bash
cloudflared tunnel --config /etc/cloudflared/config.yml run lumina-events-vps
```

Si el sitio funciona en:

```text
https://product-manager.lumina-events.com
```

detén la prueba con `Ctrl+C` e instala el servicio:

```bash
sudo cloudflared --config /etc/cloudflared/config.yml service install
sudo systemctl enable --now cloudflared
```

Comprobar el estado y los logs:

```bash
sudo systemctl status cloudflared
sudo journalctl -u cloudflared -f
```

El error Cloudflare `1033` indica que el DNS apunta al túnel, pero `cloudflared` no está conectado o ejecutándose.

