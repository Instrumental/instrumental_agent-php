WARNING: WORK IN PROGRESS. NOT PRODUCTION READY.

brew install php70 --with-thread-safety http://www.smddzcy.com/2016/01/installing-configuring-php7-zts-on-os-x/
http://jason.pureconcepts.net/2012/10/install-pear-pecl-mac-os-x/
pecl install pthreads (or should we brew)

php -r "readfile('https://getcomposer.org/installer');" > composer-setup.php
php -r "if (hash('SHA384', file_get_contents('composer-setup.php')) === '7228c001f88bee97506740ef0888240bd8a760b046ee16db8f4095c0d8d525f2367663f22a46b48d072c816e7fe19959') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php
php -r "unlink('composer-setup.php');"
