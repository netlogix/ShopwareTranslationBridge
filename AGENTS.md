## Do
- use this docker image to test ghcr.io/netlogix/docker/php-cli-dev:8.4
- Run tests with `composer run test`
- Run `composer run apply-coding-standard` after code changes and before test
- Run `composer run lint` and fix issues after changes and after tests 

## Commands

### composer run with php 8.4
```bash
docker run --rm -it -v"$(pwd):/var/www" ghcr.io/netlogix/docker/php-cli-dev:8.4 composer run 
```

### composer run with php 8.4
```bash
docker run --rm -it -v"$(pwd):/var/www" ghcr.io/netlogix/docker/php-cli-dev:8.4 composer run 
```
    