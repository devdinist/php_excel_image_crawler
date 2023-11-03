<?php

namespace dinist\php_excel_image_crawler;

/**
 * 이미지 크롤링 상수 Backed Enum
 * <i>2023.09.18 - dinist</i>
 * @author dinist <dinist@naver.com>
 * @copyright 2023 dinist
 */
enum Consts: string{
    case HTTP = "http:";
    case HTTPS = "https:";
}