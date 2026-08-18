# Usar a imagem oficial do PHP com Apache
FROM php:8.2-apache

# Habilita os módulos rewrite e headers do Apache (necessários para .htaccess)
RUN a2enmod rewrite headers

# Instala as extensões PDO e PDO MySQL exigidas para conectar no banco de dados do Aiven
RUN docker-php-ext-install pdo pdo_mysql

# Define o diretório de trabalho do Apache
WORKDIR /var/www/html

# Copia os arquivos do projeto para dentro do contêiner
COPY . /var/www/html/

# Ajusta as permissões de gravação para o Apache
RUN chown -R www-data:www-data /var/www/html

# Expõe a porta padrão do Apache
EXPOSE 80
