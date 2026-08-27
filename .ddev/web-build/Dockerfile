ARG BASE_IMAGE
FROM $BASE_IMAGE

ENV EXTENSION_KEY="kern_ux"
ENV DDEV_SITENAME="kern-ux"

# Placeholder docroots so the vhosts resolve before TYPO3 is installed
RUN rm -f /var/www/html/index.html \
 && for V in v13 v14; do \
      mkdir -p /var/www/html/$V/public/typo3; \
      echo "<h1>Run <code>ddev install-$V</code> first</h1>" > /var/www/html/$V/public/index.html; \
      echo "<h1>Run <code>ddev install-$V</code> first</h1>" > /var/www/html/$V/public/typo3/index.html; \
    done

RUN printf '%s\n' \
  '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">' \
  '<title>EXT:kern_ux Dev</title>' \
  '<style>body{font-family:system-ui,sans-serif;max-width:52rem;margin:4rem auto;padding:0 1rem;line-height:1.6}' \
  'a{color:#06c}code{background:#eee;padding:.1em .4em;border-radius:3px}</style></head><body>' \
  '<h1>EXT:kern_ux &mdash; KERN UX-Standard f&uuml;r TYPO3</h1>' \
  '<h2>Umgebungen</h2><ul>' \
  '<li>TYPO3 13.4 LTS &mdash; <a href="https://v13.kern-ux.ddev.site/">Frontend</a> &middot; <a href="https://v13.kern-ux.ddev.site/typo3/">Backend</a></li>' \
  '<li>TYPO3 14.3 LTS &mdash; <a href="https://v14.kern-ux.ddev.site/">Frontend</a> &middot; <a href="https://v14.kern-ux.ddev.site/typo3/">Backend</a></li>' \
  '</ul><h2>Zugang</h2><p><code>admin</code> / <code>Joh316!!</code></p>' \
  '<h2>Befehle</h2><ul><li><code>ddev install-v13</code></li><li><code>ddev install-v14</code></li>' \
  '<li><code>ddev install-all</code></li></ul></body></html>' \
  > /var/www/html/index.html

ARG uid
ARG gid
RUN chown -R $uid:$gid /var/www/html
