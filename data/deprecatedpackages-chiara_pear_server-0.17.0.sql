ALTER TABLE packages CHANGE channel channel varchar(255) NOT NULL default '';
ALTER TABLE packages ADD deprecated_package varchar(80) NOT NULL default '';
ALTER TABLE packages ADD deprecated_channel varchar(255) NOT NULL default '';