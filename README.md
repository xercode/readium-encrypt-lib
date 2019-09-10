Encryption tool
===============
The command tool to encrypted epubs and pdf files

#Configuration
Use the .env file or use export 

```bash
export APP_ENV='prod'
export ENCRYPT_TOOL=/workspace/go-work/bin/lcpencrypt
export AWS_AWS_ACCESS_KEY_ID='user'
export AWS_SECRET_ACCESS_KEY='12345678'
export AWS_S3_BUCKET='backetname'
export AWS_S3_REGION='eu-west-1'
export LICENSE_SERVER_ENDPOINT="http://127.0.0.1:8989"
export LICENSE_SERVER_PROFILE="basic"
export LICENSE_SERVER_USERNAME="username"
export LICENSE_SERVER_PASSWORD="password"
```

## Basic usage 

```bash
bin/encrypt file:///workspace/licensedContentProtection/readium-encrypt-lib/tests/fixtures/9788415410478.epub 
```

## Encrypted files from s3
```bash  
bin/encrypt s3://210/210_1567702120_5d713c68c4fbc_5d713c68c5068.pdf
``` 

## Encrypted files from s3 and send License server 
```bash  
bin/encrypt s3://210/210_1567702120_5d713c68c4fbc_5d713c68c5068.pdf -s 
``` 
