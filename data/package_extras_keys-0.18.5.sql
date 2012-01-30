# Table alterations
UPDATE package_extras SET package="" WHERE package=NULL;
ALTER TABLE package_extras CHANGE package package varchar(80) NOT NULL default '';
ALTER TABLE package_extras ADD PRIMARY KEY (channel, package);