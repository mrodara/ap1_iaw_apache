# Laboratorio LAMP con Docker Compose

Este repositorio monta una pequeña pila LAMP para practicar Docker y Git: Apache sirve una página estática y una página PHP que consulta MySQL. Sigue la ruta rápida para arrancarlo; las secciones posteriores explican cómo está construido, cómo inspeccionarlo y cómo trabajar con sus cambios.

## Ruta rápida

### 1. Requisitos

- Git.
- Docker Engine con el complemento `docker compose`, o Docker Desktop con Compose.
- Un terminal y un navegador.

Comprueba que Docker responde:

```bash
docker --version
docker compose version
```

### 2. Clonar y entrar al repositorio

La URL configurada como remoto `origin` en este proyecto es `https://github.com/mrodara/ap1_iaw_apache.git`.

```bash
git clone https://github.com/mrodara/ap1_iaw_apache.git
cd ap1_iaw_apache
```

Si recibiste otra URL, sustituye la del comando `git clone` por la URL que te haya proporcionado tu docente. El directorio creado normalmente tendrá el mismo nombre que el repositorio; ajusta `cd` si lo clonaste con otro nombre.

### 3. Configurar las variables locales

Compose necesita cuatro variables para configurar MySQL y la conexión PHP. Crea un archivo `.env` en la raíz del repositorio con valores de laboratorio:

```dotenv
DB_ROOT_PASSWORD=cambia_esta_clave_root
DB_NAME=iaw
DB_USER=iaw_user
DB_PASSWORD=cambia_esta_clave_app
```

El archivo `.env` no está incluido en el repositorio. Usa contraseñas distintas en cada entorno y no publiques ni compartas este archivo.

### 4. Resolver los nombres de los sitios

Apache selecciona el sitio por el nombre de host. Para abrir las URL indicadas más abajo, configura estos nombres en el archivo `hosts` del sistema para que apunten a tu propio equipo:

```text
127.0.0.1 static.iaw.test dynamic.iaw.test
```

Ubicaciones habituales:

| Sistema | Archivo |
|---|---|
| Linux y macOS | `/etc/hosts` |
| Windows | `C:\Windows\System32\drivers\etc\hosts` |

Editar ese archivo suele requerir permisos de administrador. Si no puedes editarlo, puedes comprobar ambos sitios con `curl` enviando manualmente el encabezado `Host` (ver “Verificación”); el navegador no resolverá los nombres automáticamente sin la entrada.

### 5. Construir y arrancar

Desde la carpeta que contiene `compose.yml` y `.env`:

```bash
docker compose config --quiet
docker compose up --build -d
docker compose ps
```

La aplicación web espera a que el chequeo de salud de MySQL sea correcto antes de iniciarse.

### 6. Verificar los servicios

Abre estas direcciones en el navegador:

- Estática: <http://static.iaw.test:8080/>. Debe aparecer “SITIO ESTÁTICO”.
- Dinámica: <http://dynamic.iaw.test:8080/>. Deben aparecer dos mensajes procedentes de la tabla `messages` de MySQL.
- Página estática secundaria: <http://static.iaw.test:8080/otra.html>.

También puedes comprobar los hosts virtuales desde el terminal sin modificar `hosts`:

```bash
curl --fail --show-error -H 'Host: static.iaw.test' http://127.0.0.1:8080/
curl --fail --show-error -H 'Host: dynamic.iaw.test' http://127.0.0.1:8080/
```

Consulta los registros, el estado y la tabla:

```bash
docker compose ps
docker compose logs --tail=100 web db
docker compose exec db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=TCP -h 127.0.0.1 -u root -D "$MYSQL_DATABASE" -e "SELECT id, message FROM messages ORDER BY id;"'
```

## Qué contiene el repositorio

```text
.
├── .gitignore
├── Dockerfile
├── compose.yml
├── apache/
│   ├── 001-static.conf
│   ├── 002-dynamic.conf
│   └── initdb/
│       └── 01-seed.sql
└── sites/
    ├── static/
    │   ├── index.html
    │   └── otra.html
    └── dynamic/
        └── index.php
```

| Archivo | Función |
|---|---|
| `.gitignore` | Excluye `.env`, que contiene variables locales, y `.atl` del seguimiento de Git. |
| `Dockerfile` | Define la imagen web basada en `php:8.4-apache`, instala `mysqli`, copia los dos sitios y las configuraciones de Apache, concede permisos de lectura, desactiva el sitio por defecto, activa los dos hosts virtuales y valida Apache con `apache2ctl configtest`. Expone el puerto de contenedor `80` y ejecuta `apache2-foreground`. |
| `compose.yml` | Declara los servicios `web` y `db`, sus variables, el puerto `8080:80`, la red `lamp`, el volumen persistente de MySQL, el SQL de inicialización y el chequeo de salud de la base de datos. |
| `apache/001-static.conf` | Configura `static.iaw.test`, su raíz `/var/www/static` y `index.html` como página de inicio. |
| `apache/002-dynamic.conf` | Configura `dynamic.iaw.test`, su raíz `/var/www/dynamic` y `index.php` como página de inicio. |
| `apache/initdb/01-seed.sql` | Crea la tabla `messages` e inserta dos mensajes de ejemplo. MySQL ejecuta este archivo al inicializar una carpeta de datos vacía. |
| `sites/static/index.html` | Página principal estática servida directamente por Apache; no consulta MySQL. |
| `sites/static/otra.html` | Segunda página estática para probar una ruta adicional. |
| `sites/dynamic/index.php` | Conecta a MySQL con `DB_HOST`, `DB_USER`, `DB_PASSWORD` y `DB_NAME`, muestra los mensajes escapando el HTML y responde con HTTP 503 si falla el backend. |

## Cómo se relacionan Docker, Compose y la aplicación

- **Dockerfile**: instrucciones para construir la imagen web, incluyendo PHP, Apache, módulos, configuraciones y archivos del sitio.
- **Imagen**: resultado construido a partir del Dockerfile. Es una plantilla; por sí sola no está ejecutando la aplicación.
- **Contenedor**: proceso aislado creado desde una imagen. En este proyecto se ejecutan contenedores separados para `web` y `db`.
- **Compose**: describe y coordina ambos servicios, sus variables, puertos, dependencia, red y almacenamiento. `docker compose up` aplica esa descripción.
- **Red `lamp`**: permite que los contenedores se comuniquen. PHP llega a MySQL usando el nombre de servicio `db`; no hace falta publicar MySQL en el equipo.
- **Volumen `db_data`**: conserva los archivos de MySQL aunque se sustituya o detenga el contenedor de base de datos. El SQL de inicialización no se vuelve a ejecutar mientras ese volumen ya contenga datos.

Compose construye `web` localmente con `build: .`; `db` utiliza `mysql:8.4`. El puerto del equipo `8080` se conecta al puerto `80` del contenedor web. El puerto `80` que aparece en `EXPOSE` documenta el puerto previsto dentro de la imagen; la publicación efectiva está definida por `8080:80` en Compose.

## Ciclo de vida, datos y cambios

Comandos habituales, ejecutados desde la raíz del proyecto:

```bash
# Ver configuración efectiva (requiere el archivo .env)
docker compose config

# Seguir registros en tiempo real; Ctrl+C sale del seguimiento
docker compose logs -f web db

# Detener y retirar los contenedores y la red de Compose
docker compose down

# Volver a iniciar los servicios existentes
docker compose up -d

# Reconstruir la imagen web después de cambiar Dockerfile, HTML, PHP o configuración Apache
docker compose up --build -d

# Solicitar una reconstrucción sin reutilizar capas de construcción
docker compose build --no-cache web
docker compose up -d
```

Los archivos del sitio y la configuración Apache se copian a la imagen durante la construcción. Por eso, después de editarlos, reconstruye `web` para que el contenedor use los cambios. El SQL se monta como archivo de solo lectura en la ubicación de inicialización de MySQL, pero solo se ejecuta cuando MySQL crea por primera vez su directorio de datos.

`docker compose down` conserva el volumen nombrado `db_data`. Por tanto, cambiar `DB_NAME`, usuarios, contraseñas o el archivo SQL en `.env`/en el repositorio no reconfigura automáticamente una base ya inicializada. Para conservarla, inspecciona y migra los datos conscientemente; no borres el volumen como método de depuración rutinario.

### Limpieza: atención a la pérdida de datos

El siguiente comando elimina también los volúmenes declarados por este proyecto y borra de forma irreversible los datos locales guardados en `db_data`:

```bash
docker compose down --volumes
```

Úsalo únicamente si quieres reiniciar MySQL desde cero y aceptas perder los datos del volumen. En ese siguiente arranque se volverá a crear la base y se ejecutará `01-seed.sql`. No añadas `--volumes` por costumbre.

## Flujo de Git para el laboratorio

Git registra cambios en el repositorio; Docker construye y ejecuta la aplicación. Puedes versionar Dockerfile, Compose, SQL, configuraciones y código, pero no debes versionar el archivo `.env` local con credenciales.

### Revisar y guardar un cambio

```bash
git status
git diff
git add sites/static/index.html
git diff --staged
git commit -m "docs: describe static page"
git log --oneline --decorate -5
```

`status` muestra archivos modificados y preparados. `diff` permite revisar los cambios aún no preparados; `add` selecciona qué incluir en el próximo commit; `diff --staged` revisa esa selección. Un `commit` guarda una instantánea local con un mensaje que explica el cambio. `log` enseña el historial. En el ejemplo, cambia la ruta preparada para que corresponda a tu propio trabajo.

### Trabajar en una rama y combinar el resultado

Una rama permite experimentar sin mover la rama principal mientras el cambio está en curso:

```bash
git switch -c practica/cambio-estatico
# Edita un archivo y revisa los cambios
git status
git diff
git add <ruta-del-archivo>
git diff --staged
git commit -m "docs: explain static site"
```

Cuando quieras integrar esa rama localmente:

```bash
git switch main
git pull --ff-only origin main
git merge --no-ff practica/cambio-estatico
git log --oneline --graph --decorate -8
```

`merge` combina la rama indicada con la rama actual (`main` en este ejemplo). Si Git informa de conflictos, abre cada archivo marcado, decide qué contenido debe quedar, elimina los marcadores de conflicto, guarda el archivo y luego ejecuta `git add <ruta>` y `git commit` para completar la combinación. No borres cambios ajenos para resolver un conflicto.

La rama principal de un repositorio puede llamarse `main` o `master`. Comprueba el nombre disponible con `git branch --list` y sustituye `main` en los ejemplos si el repositorio usa otro.

### Sincronizar con GitHub

`pull` trae cambios remotos y los integra en la rama actual; `push` publica commits locales en el remoto. Revisa antes de sincronizar:

```bash
git remote -v
git status
git pull --ff-only origin main
```

Para publicar una rama propia, usa el nombre de tu rama:

```bash
git push -u origin practica/cambio-estatico
```

`-u` configura el remoto de seguimiento para que posteriores `git push` y `git pull` puedan omitir esos argumentos. Publicar en una rama compartida depende de los permisos y normas del repositorio del curso. No fuerces un `push` para ocultar divergencias: primero revisa el estado y coordina cómo integrar el historial.

## Prácticas sugeridas

1. Cambia el texto de `sites/static/index.html`, reconstruye `web` y verifica que el navegador muestra el cambio.
2. Edita `sites/dynamic/index.php` para añadir una etiqueta HTML y observa cómo su salida depende de MySQL.
3. Añade una fila de ejemplo a `apache/initdb/01-seed.sql`. Explica por qué no aparece en una base ya inicializada y qué diferencia hay entre conservar o eliminar `db_data`.
4. Modifica temporalmente un nombre `ServerName`, reconstruye y comprueba qué efecto tiene el encabezado `Host`.
5. Crea una rama, realiza dos commits pequeños y representa el historial con `git log --oneline --graph --all`.
6. Practica una combinación en una rama de prueba. Si aparece un conflicto, resuélvelo revisando el contenido de ambos lados.

## Diagnóstico basado en este proyecto

| Síntoma | Comprobaciones |
|---|---|
| `docker compose config` informa de variables sin definir | Confirma que `.env` está en la misma carpeta que `compose.yml` y contiene las cuatro variables indicadas. |
| No abre `static.iaw.test` o `dynamic.iaw.test` | Comprueba la entrada del archivo `hosts`, que los servicios están activos con `docker compose ps` y que la URL incluye `:8080`. También prueba `curl` con el encabezado `Host`. |
| Ambos sitios parecen iguales o se muestra un host inesperado | Los dos sitios comparten el puerto y Apache los distingue por el nombre de host. Revisa el nombre escrito en la URL y los `ServerName` de `apache/001-static.conf` y `apache/002-dynamic.conf`. |
| La página dinámica devuelve “Servicio temporalmente no disponible” | Revisa `docker compose ps`, `docker compose logs db web` y el estado saludable de MySQL. Comprueba que `.env` contiene credenciales coincidentes y recuerda que cambiar credenciales no modifica una base existente. |
| La tabla `messages` no existe o faltan filas | Revisa `docker compose logs db` y `apache/initdb/01-seed.sql`. El SQL de inicialización solo se ejecuta con un directorio de datos vacío; no elimines el volumen sin decidir antes qué ocurrirá con los datos. |
| El cambio de HTML, PHP o Apache no aparece | Esos archivos se copian a la imagen web. Reconstruye con `docker compose up --build -d` y revisa los registros. |
| Git no permite hacer `pull --ff-only` | La rama local y la remota han divergido. Inspecciona `git status` y `git log --oneline --graph --decorate --all` antes de elegir cómo integrar el trabajo. |

## Seguridad

- Mantén `.env` local y fuera de commits, capturas y repositorios públicos; contiene contraseñas.
- Las contraseñas de ejemplo son solo para un laboratorio local. No reutilices credenciales reales ni las de otros servicios.
- La base de datos no publica un puerto al equipo en este Compose. El sitio web sí se publica en `8080`; limita el acceso al entorno de confianza donde practicas.
- La página PHP no muestra el detalle de excepciones al visitante, pero registra el error en el log de Apache. Los registros pueden incluir datos operativos: no los publiques sin revisarlos.
- `docker compose down --volumes` elimina el almacenamiento de MySQL del proyecto. Haz copia de los datos antes de cualquier limpieza que no puedas revertir.

## Lista de reproducibilidad

Antes de entregar el laboratorio, verifica que otra persona pueda repetirlo:

- [ ] Clonó el repositorio desde una URL conocida y está en la carpeta correcta.
- [ ] Tiene Docker y `docker compose` disponibles.
- [ ] Creó el `.env` local con las cuatro variables requeridas y no lo añadió a Git.
- [ ] Configuró los dos nombres virtuales en `hosts` o conoce el método de prueba con `curl`.
- [ ] `docker compose config --quiet` termina sin error.
- [ ] `docker compose up --build -d` inicia los servicios y MySQL aparece saludable.
- [ ] La página estática, la secundaria y la página dinámica responden como se espera.
- [ ] Revisó los registros y comprobó que la base tiene las filas iniciales.
- [ ] Entiende que `down` conserva los datos y que `down --volumes` los elimina.
- [ ] Revisó con `git status` qué archivos entregará y confirmó que no incluye `.env`.
