# api.esports.moe

## setup
### 1. install [composer](https://getcomposer.org/)
```
composer install
```

### 2. install php7 and apache2
```
sudo add-apt-repository ppa:ondrej/php
sudo apt-get update
sudo apt-get install apache2 php7
```

### 3. run the dev server
some simple scripts are provided for running apache2 in your
current directory / in the foreground (`pache`), and for making
the error log of apache more readable (`ppl`).
```
./scripts/pache 8080 . | ./scripts/ppl
```
Neither of these are strictly necessary but it will make getting
started with development much faster.

## meta
### branching strategy
- create a new branch for each feature
- submit pull requests to `develop`
- periodically merge `develop` into `master` on release
