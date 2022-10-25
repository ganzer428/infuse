
#### INSTALLATION
Extract contents to any folder on a web-server
and edit .env file which needs to have project web root and DB details, 
pretty straightforward. Test pages on my dev server are as follows:

https://de.au.nu/infuse/index1.html

https://de.au.nu/infuse/index2.html

Here you can see the dump of the table

https://de.au.nu/infuse/dump.php

Current SQL attached as 

    data.sql.gz

***
#### FEW NOTES

I used couple of simple 'homemade' classes which I use to build
web pages from the scratch, like DB operations, config, handling 
POST/GET requests etc., these are few .php files in /clas

Had to rename 'banner.php' to 'bimage.php' as otherwise it was 
blocked by AdBlocker in my browsers, so it was easier to rename than 
to fix it in all settings of the ones I used to test (Chrome/Safari)

Added extra field to the table 'page_md5' which is md5(page_url) 
to be able to index/select data by page url as in theory path could 
be longer than 255 
I suppose there could be different images depending on 
ad campaign and more fields in the table, so actual image url
is like: 

    bimage.php?campaign=12345

and various images are displayed depending on that, but as 
far as there was nothing in description the image is one for all

SQL dump got records where page_url is empty - it happens when the
images is opened as a straight URL, without following the link
which is the natural behaviour I guess