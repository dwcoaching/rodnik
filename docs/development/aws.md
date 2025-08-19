
```
BASE_DIR="/home/rodnik/rodnik.today"
DUMP_NAME="$(TZ=":Europe/Moscow" date +"%Y-%m-%d_%H:%M:%S").sql"
MY_CNF=".my.cnf"
DUMPS_DIRECTORY="storage/dumps"
DUMPS_DELETE_AFTER=31
FILES_DIRECTORY="storage/app"
DATABASE="rodnik"
S3_BUCKET="s3://rodnik.today"
S3_DUMPS_PATH="mysql"
S3_FILES_PATH="files"
S3_FILES_DIRECTORY="$(TZ=":Europe/Moscow" date +"%Y-%m-%d_%H:%M:%S")"

cd $BASE_DIR

mysqldump --defaults-file=$MY_CNF $DATABASE --no-tablespaces > $DUMPS_DIRECTORY/$DUMP_NAME
aws s3 cp $DUMPS_DIRECTORY/$DUMP_NAME $S3_BUCKET/$S3_DUMPS_PATH/latest.sql
aws s3 cp $S3_BUCKET/$S3_DUMPS_PATH/latest.sql $S3_BUCKET/$S3_DUMPS_PATH/$DUMP_NAME

find * -name "*.sql" -type f -mtime +$DUMPS_DELETE_AFTER -delete

aws s3 sync $FILES_DIRECTORY/ $S3_BUCKET/$S3_FILES_PATH/
```




aws s3 sync s3://rodnik.today/files/ /home/rodnik/rodnik.today/storage/app/
aws s3 cp s3://rodnik.today/mysql/latest.sql /home/rodnik/rodnik.today/storage/dumps
mysql --defaults-file=~/rodnik.today/.my.cnf < ~/rodnik.today/storage/dumps/latest.sql