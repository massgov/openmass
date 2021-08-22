FROM mysql:5.7

RUN ["mkdir", "–p", "/var/lib/mysql-image"]

RUN echo "[mysqld]" > /etc/mysql/conf.d/mass-vm.cnf \
   && echo "datadir = /var/lib/mysql-image" >> /etc/mysql/conf.d/mass-vm.cnf
