FROM mariadb:10.5

# Change datadir so DDEV's volume becomes unused.
RUN ["mkdir", "–p", "/var/lib/mysql-data-mass"]
RUN echo "[mysqld]" > /etc/mysql/conf.d/mass-vm.cnf \
   && echo "datadir = /var/lib/mysql-data-mass" >> /etc/mysql/conf.d/mass-vm.cnf