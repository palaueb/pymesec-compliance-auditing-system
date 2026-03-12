# PymeSec

Bootstrap tecnic inicial del repositori principal segons el PRD i els ADR.

L'estat actual deixa preparat:

- `core/` com a aplicacio Laravel del nucli
- desenvolupament local amb Docker
- serveis locals minims: `nginx`, `php-fpm`, `postgres`, `redis`
- scripts de treball via `Makefile`
- CI minima per validar instal·lacio, lint i tests

Encara fora d'abast:

- logica de negoci de compliance
- implementacio de plugins funcionals mes enlla del seu esquelet
- UI funcional de producte

## Requisits previs

- Docker
- Docker Compose
- GNU Make

## Arrencada rapida

1. Copia la configuracio d'entorn de l'arrel:

```bash
cp .env.example .env
```

2. Copia la configuracio del core:

```bash
cp core/.env.example core/.env
```

3. Arranca l'entorn:

```bash
make up
```

4. Executa les migracions del core:

```bash
make migrate
```

5. Comprova que Laravel respon:

```bash
curl http://localhost:8080/up
curl http://localhost:8080/
```

## Comandes habituals

```bash
make up
make down
make shell
make migrate
make test
make lint
make logs
```

## Notes d'entorn

- El `core` fa servir PostgreSQL i Redis en local.
- Els tests base corren amb SQLite en memoria per mantenir-los rapids i aillats.
- El `core` no implementa encara cap model d'identitat funcional; s'ha retirat el `User` per defecte de Laravel per respectar els ADR.

## Suposits temporals documentats

- Laravel s'instal·la directament dins `core/` com a aplicacio principal del nucli.
- El manifest de plugins continua temporalment a `plugin.json` fins que es tanqui definitivament aquesta decisio.
- El desenvolupament local es resol amb `nginx + php-fpm + postgres + redis`.
- La CI minima valida instal·lacio, migracions, lint i tests, pero no encara matrius de compatibilitat de plugins.
