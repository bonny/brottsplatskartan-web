# Utökar serversideup/php:8.4-fpm-nginx med PHP-extensions som
# brottsplatskartan kräver men som inte ingår i lean-basen:
#
#   - bcmath: league/geotools (avståndsberäkningar)
#   - exif:   bildmetadata
#   - gd:     bildmanipulation (kartbild-generering)
#
# serversideup tillhandahåller mlocati/docker-php-extension-installer
# som install-php-extensions – snabbt, cachat, prekompilerat.
#
# Bygg-tid: ~30 sek, body cachas så efter första build går det direkt.

FROM serversideup/php:8.4-fpm-nginx

USER root
RUN install-php-extensions bcmath exif gd
USER www-data
