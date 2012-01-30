# Table alterations
ALTER TABLE handles ADD channel varchar(100) NOT NULL default '' FIRST;
ALTER TABLE handles DROP PRIMARY KEY;
ALTER TABLE handles ADD PRIMARY KEY (channel, handle);