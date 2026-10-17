# ==============================================================================
# MES ORCHIDEES — Image applicative PHP 8.2 / Apache (Render, Docker)
# ------------------------------------------------------------------------------
# Phase 04 — préparation déploiement (le déploiement lui-même reste INTERDIT
# sans autorisation distincte). Points clés :
#   * DocumentRoot = public/ (front-controller) : src/, config/, templates/,
#     data/ restent HORS docroot et inaccessibles par le web (décision D4).
#   * mod_rewrite activé + AllowOverride All : le public/.htaccess route tout
#     vers index.php ; FallbackResource en secours si le .htaccess est ignoré.
#   * Extensions PDO MySQL uniquement (cible MySQL/MariaDB actée en décision D3 ;
#     les pilotes PostgreSQL/Neon de l'ancienne architecture sont retirés).
#   * Aucun secret embarqué : .env est supprimé de l'image, la config vient des
#     variables d'environnement Render (voir .env.production.example).
# ==============================================================================
FROM php:8.2-apache

# 1) Extensions PHP — PDO MySQL + mbstring + réécriture d'URL.
#    mbstring est indispensable : le code appelle mb_strlen()/mb_strtoupper()
#    (OrchidRepository, ConseilRepository, OrchidController). L'image officielle
#    php:8.2-apache ne l'embarque PAS par défaut -> sinon fatal "undefined function".
#    mbstring (avec mbregex) se compile contre oniguruma : le paquet système
#    libonig-dev est requis au build, faute de quoi `docker-php-ext-install mbstring`
#    échoue ("Package 'oniguruma' ... not found"). Constat vérifié par build réel.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql mbstring \
    && a2enmod rewrite

# 2) VirtualHost : DocumentRoot dédié à /var/www/html/public + droits .htaccess.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN { \
      echo '<VirtualHost *:80>'; \
      echo '    ServerName localhost'; \
      echo "    DocumentRoot ${APACHE_DOCUMENT_ROOT}"; \
      echo '    <Directory '"${APACHE_DOCUMENT_ROOT}"'>'; \
      echo '        DirectoryIndex index.php index.html'; \
      echo '        AllowOverride All'; \
      echo '        Require all granted'; \
      echo '        FallbackResource /index.php'; \
      echo '    </Directory>'; \
      echo '    ErrorLog ${APACHE_LOG_DIR}/error.log'; \
      echo '    CustomLog ${APACHE_LOG_DIR}/access.log combined'; \
      echo '</VirtualHost>'; \
    } > /etc/apache2/sites-available/000-default.conf \
    && a2ensite 000-default.conf

# 3) Copie du code applicatif.
COPY . /var/www/html/

# 3b) Autoloader Composer standard (aucune dépendance externe : uniquement la
#     carte PSR-4 locale App\->src/ et Mithridatem\Routing\->lib/). `dump-autoload`
#     ne requiert ni réseau ni paquets — il génère vendor/autoload.php à partir de
#     composer.json. Évite de reposer sur le rechargeur de secours de developpeur.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev --working-dir=/var/www/html

# 4) Fichiers sensibles hors image + permissions utilisateur Apache.
#    Les variables d'environnement (DB, secrets) sont injectées par Render au runtime.
RUN rm -f /var/www/html/.env /var/www/html/.env.example /var/www/html/.env.production.example \
    && chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

# 5) Port d'écoute. Sur Render, renseigner « Server Port » = 80 (ou adapter
#    Listen via $PORT si la plateforme impose un port dynamique).
EXPOSE 80

CMD ["apache2-foreground"]
