=== PW Cookie Monster ===
Contributors: pw-cookie-monster
Tags: cmp, gdpr, consent, analytics, cookies
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 0.5.8
License: GPLv2 or later

== Description ==
PW Cookie Monster es un CMP profesional con un guino divertido: el monstruo de las cookies protege tu cumplimiento y deja migas de datos limpios para tu analitica. Ideal para agencias que necesitan fiabilidad y control.

== Features ==
* Consent Mode v2 con actualizacion automatica al aceptar/rechazar.
* Bloqueo de scripts e iframes por categoria/servicio.
* Bloqueo opcional de imagenes y estilos externos.
* Escaneo de sitemap + crawling limitado para descubrir dominios externos.
* Deteccion de cookies via Set-Cookie y autocategorizacion.
* Auditoria opcional en navegador para detectar cookies reales (JS).
* Clasificacion de dominios con override manual.
* Registro de consentimiento y export CSV.
* Informe de cumplimiento (HTML/JSON).
* Geo-targeting (EEE/UK/CH, custom o global).
* Textos multilenguaje ES/EN.
* Plantillas visuales y preview en vivo.
* White-label e import/export de ajustes.
* Sin llamadas externas ni librerias pesadas.

== Installation ==
1. Sube el plugin y activalo.
2. Ajustes -> PW Cookie Monster para opciones.
3. (Opcional) Ejecuta "Escanear ahora" para detectar dominios.
4. Configura textos, categorias y apariencia.

== Usage ==
* Usa el shortcode [agency_shield_cookie_policy] para generar una politica de cookies basica.
* Usa el shortcode [agency_shield_consent_review] para mostrar un boton de revision de consentimiento.

== Screenshots ==
1. Panel principal y KPIs.
2. Editor de apariencia con preview.
3. Scanner de dominios y cookies detectadas.
4. Banner en front.

== FAQ ==
= ¿Se puede actualizar desde un servidor propio? =
Si, configura el servidor central en la pestana Tools > Actualizaciones seguras.

== Upgrade Notice ==
= 0.5.1 =
Mejoras de autocategorizacion, bloqueo adicional y notas legales.

== Privacy ==
* El CMP se ejecuta en el servidor del cliente.
* No se transfieren datos a terceros.
* El registro de consentimiento almacena hash de IP (no IP en claro).

== Changelog ==
= 0.5.8 =
* Correccion de posicion del banner (respeta alineacion configurada).

= 0.5.7 =
* Bloqueo de scripts inline de trackers conocidos (Hotjar/Clarity/GA/Ads/etc).
* Mejora de autocategorizacion de cookies necesarias.

= 0.5.6 =
* Clasificacion mejorada de cookies de consentimiento.
* Informe con conteo de cookies detectadas por categoria.

= 0.5.5 =
* Auditoria en navegador mas robusta (sin scripts inline).
* Mejora del feedback en tiempo real del escaneo.

= 0.5.4 =
* Auditoria en navegador para detectar cookies reales (JS).
* Mejora de fiabilidad del informe de cookies.

= 0.5.3 =
* Interfaz mas neutra y profesional.
* GTM eliminado del panel (Site Kit recomendado).
* Icono visible por defecto en el banner inicial.

= 0.5.2 =
* Rebranding a PW Cookie Monster y narrativa en el panel.
* Iconos oficiales del monstruo de las cookies.

= 0.5.1 =
* Autocategorizacion ampliada (GA4, Ads, Hotjar, Clarity, LinkedIn, Pinterest).
* Deteccion de cookies via Set-Cookie durante el escaneo.
* Bloqueo opcional extendido a img/link externos.
* Nota legal para cookies necesarias y log del cambio.

= 0.5.0 =
* Auto-actualizaciones seguras desde servidor central (firma + checksum).
* Mejora de deteccion y autocategorizacion de cookies durante el escaneo.

= 0.4.4 =
* Correccion de carga del banner al permitir desactivar necesarias.
* Preview del banner estable via iframe en el panel.

= 0.4.3 =
* Preview real funciona via admin-ajax.
* Correccion geo-targeting cuando lista custom esta vacia.

= 0.4.2 =
* Preview real del banner en el panel y editor de colores mejorado.
* Ajustes de categorias (permitir desactivar necesarias opcional).
* Correcciones de UI y estilos del banner.

= 0.2.0 =
* Panel profesional con preview, presets y tabs.
* Geo-targeting, multilenguaje y branding.
* Escaneo avanzado, servicios y reportes.

= 0.1.0 =
* Primera version profesional.
