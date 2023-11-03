<?php

namespace dinist\php_excel_image_crawler;

 /**
 * 이미지 크롤링 변수 Traits
 * <i>2023.09.18 - dinist</i>
 * @author dinist <dinist@naver.com>
 * @copyright 2023 dinist
 */
trait AbsoluteVariables {

    /**
     * curl 핸들러 수 설정
     * @var int
     */
    private int $handlerCount = 12;


    /**
     * curl 요청시 요청 타임아웃 시간 설정
     * @var int
     */
    private int $requestTimeout = 5;

    /**
     * 읽어올 엑셀파일명 지정
     * src/excel 폴더에 있어야함
     * @var string
     */
    private string $excelFileName = "file.xlsx";

    /**
     * 다운로드될 폴더의 이름 지정 (기본값 download)
     * src 디렉터리에 생성됨
     * @var string
     */
    private string $download_path = "download";

    /**
     * 다운로드 성공한 이미지 리스트 로그 파일명 지정
     * src 디렉터리에 생성됨
     * @var string
     */
    private string $success_log_path = "img_success_list.txt";

    /**
     * 다운로드 실패한 이미지 리스트 로그 파일명 지정
     * src 디렉터리에 생성됨
     * @var string
     */
    private string $fail_log_path = "img_fail_list.txt";

    /**
     * 중복된 이미지 리스트 로그 파일명 지정
     * src 디렉터리에 생성됨
     * @var string
     */
    private string $dup_log_path = "img_dup_list.txt";

    /**
     * 이미 다운로드한 이미지 리스트 로그 파일명 지정
     * src 디렉터리에 생성됨
     * @var string
     */
    private string $already_log_path = "img_already_list.txt";

    /**
     * 파일 다운로드 실패시 대상 파일명 사이에 추가로 삽입할 path 경로 지정
     * ex) ["500","detail"] 일때, http://test.domain.com/image.png 다운로드 실패시
     * 1. http://test.domain.com/500/image.png 으로 재시도 만약 1번에서 성공 할 경우 그 이후 path로는 시도하지 않음
     * 2. http://test.domain.com/detail/image.png 으로 재시도
     * @var array
     */
    private $new_path_array = ["500","detail"];
}