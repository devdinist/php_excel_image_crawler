# php_excel_image_crawler
* 필요한 php 버전 : >=8.1

## 사용 방법
#### 1. composer install
#### 2. dinist\excel_image_crawler\src\excel 폴더 내에 다운로드 할 엑셀파일 확인
#### 3. dinist\excel_image_crawler\src\AbsoluteVariables.php 설정 편집
   
## 사용 예시
```php
<?php

require_once __DIR__.DIRECTORY_SEPARATOR."vendor".DIRECTORY_SEPARATOR."autoload.php";

use dinist\excel_image_crawler\ImageCrawler;

$imageCrawl = new ImageCrawler();
$imageCrawl->run();

```