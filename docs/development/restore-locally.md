BASE_DIR="/Users/andrewkolpakov/code/rodnik"
MY_CNF=".my.cnf"
DUMPS_DIRECTORY="dumps"
FILES_DIRECTORY="storage/app/"
DATABASE="rodnik"
S3_BUCKET="s3://rodnik.today"
S3_DUMPS_PATH="mysql"
S3_FILES_PATH="files"

aws s3 cp $S3_BUCKET/$S3_DUMPS_PATH/latest.sql $BASE_DIR/$DUMPS_DIRECTORY/
mysql --defaults-file=$BASE_DIR/$MY_CNF $DATABASE < $BASE_DIR/$DUMPS_DIRECTORY/latest.sql

aws s3 sync $S3_BUCKET/$S3_FILES_PATH/photos/ $BASE_DIR/$FILES_DIRECTORY/photos/
aws s3 sync $S3_BUCKET/$S3_FILES_PATH/public/ $BASE_DIR/$FILES_DIRECTORY/public/