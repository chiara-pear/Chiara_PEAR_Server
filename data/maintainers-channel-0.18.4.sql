# Table alterations
ALTER TABLE categories CHANGE channel channel varchar(255) NOT NULL default '';
ALTER TABLE maintainers CHANGE channel channel varchar(255) NOT NULL default '';
ALTER TABLE handles CHANGE channel channel varchar(255) NOT NULL default '';
ALTER TABLE releases CHANGE channel channel varchar(255) NOT NULL default '';
ALTER TABLE channels CHANGE channel channel varchar(255) NOT NULL default '';
