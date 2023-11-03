<?php

namespace dinist\php_excel_image_crawler;

use DateTime;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use RuntimeException;

 /**
 * <p>이미지 크롤링 메인 클래스</p>
 * <p>2023.09.18 - dinist</p>
 * <p>엑셀파일에 있는 url 이미지 링크를 가져다가 저장</p>
 * @author dinist <dinist@naver.com>
 * @copyright 2023 dinist
 */
class ImageCrawler{

    // 주요 설정은 이 trait에서!
    use AbsoluteVariables;

    private array $excel_data;
    private int $excel_row_count;
    private array $downloaded_array = [];
    private int $success_count = 0;
    private int $fail_count = 0;
    private int $dup_count = 0;
    private int $already_count = 0;
    private int $all_count = 0;
    private int $row_number = 0;
    private $success_filepointer;
    private $fail_filepointer;
    private $dup_filepointer;
    private $already_filepointer;
    private $multi_curl;
    private array $curl = [];
    private array $url_array = [];
    private array $curl_result = [];
    private array $restart_url = [];
    private array $restart_url2 = [];
    private DateTime $startTime;
    private DateTime $endTime;

    function __construct(){

        // 시작 시간 기록
        $this->startTime = new DateTime();

        // fopen url 접근 허용
        ini_set("allow_url_fopen",1);

        // xlsx reader 추가
        $reader = new Xlsx();

        try {

            $excel = $reader->load(__DIR__.DIRECTORY_SEPARATOR."excel".DIRECTORY_SEPARATOR.$this->excelFileName);

            $this->excel_data = $excel->getActiveSheet()->toArray();
            $this->excel_row_count = $excel->getActiveSheet()->getHighestDataRow();
    
            // 이미지 다운로드 경로 생성
            @mkdir(__DIR__.DIRECTORY_SEPARATOR.$this->download_path);

        } catch ( Exception $e ){
            throw new RuntimeException("excel 폴더에 {$this->excelFileName} 파일이 존재하지 않습니다.");
        }
        
    }

    /**
     * curl 설정
     */
    private function curl_set(array $urlArray, bool $addHttpPrefix = true): void
    {

        $this->curl = [];
        $this->multi_curl = curl_multi_init();

        for($a = 0; $a < count($urlArray); $a++){
            $this->curl[$a] = curl_init();
            curl_setopt($this->curl[$a],CURLOPT_HEADER,0);
            curl_setopt($this->curl[$a],CURLOPT_RETURNTRANSFER,1);
            curl_setopt($this->curl[$a],CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($this->curl[$a],CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($this->curl[$a],CURLOPT_URL,($addHttpPrefix ? Consts::HTTP->value : "").$urlArray[$a]);

            curl_multi_add_handle($this->multi_curl,$this->curl[$a]);
        }
    }

    /**
     * curl 실행 및 결과 저장
     */
    private function curl_exec_and_result(): void
    {
        do{
            curl_multi_exec($this->multi_curl,$run);
            curl_multi_select($this->multi_curl,$this->requestTimeout);
        }while($run > 0);

        for($i = 0; $i < $this->handlerCount; $i++){
            
            $this->curl_result[$i] = [];

            if(array_key_exists($i,$this->curl)){

                $this->curl_result[$i]['content'] = curl_multi_getcontent($this->curl[$i]);
                $this->curl_result[$i]['httpcode'] = curl_getinfo($this->curl[$i],CURLINFO_HTTP_CODE);
                $this->curl_result[$i]['downloadable'] = $this->curl_result[$i]['httpcode'] === 200;
                $this->curl_result[$i]['info'] = curl_getinfo($this->curl[$i]);
    
                curl_multi_remove_handle($this->multi_curl,$this->curl[$i]);
            }
        }


        $this->curl_close();
    }

    /**
     * curl 종료
     */
    private function curl_close(): void
    {
        curl_multi_close($this->multi_curl);
    }

    /**
     * 로그 파일 포인터 오픈
     */
    private function filePointerOpen(): void
    {
        $this->success_filepointer = fopen(__DIR__.DIRECTORY_SEPARATOR.$this->success_log_path,'w+');
        $this->fail_filepointer = fopen(__DIR__.DIRECTORY_SEPARATOR.$this->fail_log_path,'w+');
        $this->dup_filepointer = fopen(__DIR__.DIRECTORY_SEPARATOR.$this->dup_log_path,'w+');
        $this->already_filepointer = fopen(__DIR__.DIRECTORY_SEPARATOR.$this->already_log_path,'w+');
    }

    /**
     * 로그 파일 포인터 닫기
     */
    private function filePointerClose(): void
    {
        fclose($this->success_filepointer);
        fclose($this->fail_filepointer);
        fclose($this->dup_filepointer);
        fclose($this->already_filepointer);
    }

    /**
     * 크롤링 시작
     */
    function run(): void
    {
        $this->filePointerOpen();
        $last = false;

        foreach($this->excel_data as $each_data):
            // row가 있으면 카운트
            $this->row_number++;
            if(is_array($each_data)):
                foreach($each_data as $each_data2):

                    // 해당 셀이 비어 있는 경우 패스
                    if(!$each_data2 || mb_strlen(trim($each_data2)) <= 0){
                        continue;
                    }
        
                    // 값이 있는 셀일 경우 카운트
                    $this->all_count++;
        
                    // 해당 셀의 URL이 중복인 경우 카운트 및 pass
                    if(in_array($each_data2,$this->downloaded_array)){
                        fwrite($this->dup_filepointer,"dup : line number is {$this->row_number} , fileurl : {$each_data2} \n");
                        $this->dup_count++;
                        continue;
                    }
        
                    $each_data2 = Consts::HTTP->value.$each_data2;
        
                    $current_fileurl = $each_data2;
                    $current_fileinfo = pathinfo($current_fileurl);
                    $current_filename = $current_fileinfo['basename'];
                    $current_filename_absolute_path = __DIR__.DIRECTORY_SEPARATOR.$this->download_path.DIRECTORY_SEPARATOR.$current_filename;

                    // 해당 셀의 파일이 이미 다운로드 되어 있을 경우 완료 카운트 및 패스
                    if(is_file($current_filename_absolute_path)){
                        fwrite($this->already_filepointer,"already downloaded : {$current_fileurl} , path : $current_fileurl\n");
                        $this->already_count++;
                        continue;
                    }

                    // 현재 셀에 url이 존재하면서, url 배열이 curl 병렬 핸들러 카운트보다 낮은경우
                    if(count($this->url_array) < $this->handlerCount && mb_strlen($current_fileurl))
                        $this->url_array[] = $current_fileurl;

                    // 현재 행이 마지막 행인 경우
                    if($this->row_number == $this->excel_row_count)
                        $last = true;
        
                endforeach;

                // 마지막행이거나 url 배열이 curl 병렬 핸들러 카운트와 같은 경우 (다운로드 시작)
                if($last || count($this->url_array) == $this->handlerCount){

                    $this->curl_set($this->url_array,false);
                    $this->curl_exec_and_result();

                    for($i = 0; count($this->curl_result) && $i < $this->handlerCount; $i++){
                        // 현재 curl 처리한 url 응답코드가 200이 아닌경우
                        if($this->curl_result[$i] && $this->curl_result[$i]['httpcode'] != 200){
                            $this->restart_url[] = $this->url_array[$i];
                        }
            
                        // 다운로드 가능한 경우 다운로드 처리
                        if($this->curl_result[$i] && $this->curl_result[$i]['downloadable']):

                            $current_fileinfo = pathinfo($this->url_array[$i]);
                            $current_filename = $current_fileinfo['basename'];
                            $current_filename_absolute_path = __DIR__.DIRECTORY_SEPARATOR.$this->download_path.DIRECTORY_SEPARATOR.$current_filename;
                            
                            $downfilepointer = fopen(__DIR__.DIRECTORY_SEPARATOR.$this->download_path.DIRECTORY_SEPARATOR.$current_filename,'wb');
                            fwrite($downfilepointer,$this->curl_result[$i]['content']);
                            fclose($downfilepointer);
            
                            fwrite($this->success_filepointer,"success : {$this->url_array[$i]} , path : {$this->url_array[$i]}\n");
            
                            $this->success_count++;

                            $this->downloaded_array[] = $this->url_array[$i];
                            
                        endif;
                    }
                    
                    // 현재 사이클에서 curl 모두 돌리고 나면 url 배열 초기화
                    $this->url_array = [];
                }
            endif;
        endforeach;
        
        // 초기 url에서 실패한적이 있어 대체 경로 삽입 해야하는 경우 (추가 path)
        for($i = 0; $i < count($this->new_path_array); $i++){
            $this->runMore($this->new_path_array[$i],$i % 2 == 0,$i == count($this->new_path_array) - 1);
        }

        $this->filePointerClose();
        $this->printResultInfo();
        echo sprintf("elapsed time is : %s\n",$this->calculateElapseTime());
    }

    // 추가 path 다운로드
    /**
     * @param string $pathKeyword 삽입할 경로 키워드 ex) 500, detail ...
     * @param bool $useFirst restart_url 배열 사용할지 restart_url2 배열 사용할지 (서로 로테이션으로 사용)
     * @param bool $isLastKeyword 마지막 키워드일경우 최종 실패시 최종 실패 로깅 하기 위한 flag 변수
     */
    private function runMore(string $pathKeyword,bool $useFirst, bool $isLastKeyword): void
    {

        $last = false;
        $restart_url = $useFirst ? $this->restart_url : $this->restart_url2;

        foreach($restart_url as $v):

            $v = ($this->checkURL($v) ? Consts::HTTP->value : "").$v;

            $current_fileurl = $v;
            $current_fileinfo = pathinfo($current_fileurl);
            $headpath = $current_fileinfo['dirname'];
            $tailpath = $current_fileinfo['basename'];

            // 키워드 삽입
            $current_fileurl = $headpath."/".$pathKeyword."/".$tailpath;
            $current_filename = $current_fileinfo['basename'];
            $current_filename_absolute_path = __DIR__.DIRECTORY_SEPARATOR.$this->download_path.DIRECTORY_SEPARATOR.$current_filename;


            // 해당 셀의 파일이 이미 다운로드 되어 있을 경우 완료 카운트 및 패스
            if(is_file($current_filename_absolute_path)){
                $this->already_count++;
                fwrite($this->already_filepointer,"duplicate : {$current_fileurl} , path : $current_fileurl\n");
                continue;
            }

            if(count($this->url_array) < $this->handlerCount)
                $this->url_array[] = $current_fileurl;

            if($this->row_number == $this->excel_row_count)
                $last = true;   

            if($last || count($this->url_array) == $this->handlerCount){

                $this->curl_set($this->url_array,false);
                $this->curl_exec_and_result();

                for($i = 0; $i < count($this->url_array) && $this->handlerCount; $i++){
                    if($this->curl_result[$i]['httpcode'] != 200){

                        if($isLastKeyword){
                            fwrite($this->fail_filepointer,"fail_origin : {$v} , last attempted url : $current_fileurl\n");
                            $this->fail_count++;
                            continue;
                        }

                        if($useFirst)
                            $this->restart_url2[] = $v;
                        else 
                            $this->restart_url[] = $v;
                    }
        
                    if($this->curl_result[$i]['downloadable']):

                        $current_fileinfo = pathinfo($this->url_array[$i]);
                        $current_filename = $current_fileinfo['basename'];
                        $current_filename_absolute_path = __DIR__.DIRECTORY_SEPARATOR.$this->download_path.DIRECTORY_SEPARATOR.$current_filename;
                        
                        $downfilepointer = fopen(__DIR__.DIRECTORY_SEPARATOR.$this->download_path.DIRECTORY_SEPARATOR.$current_filename,'wb');
                        fwrite($downfilepointer,$this->curl_result[$i]['content']);
                        fclose($downfilepointer);
        
                        fwrite($this->success_filepointer,"success : {$this->url_array[$i]} , path : {$this->url_array[$i]}\n");
        
                        $this->success_count++;

                        $this->downloaded_array[] = $this->url_array[$i];
                        
                    endif;
                }

                $this->url_array = [];
                
            }

        endforeach;

        if($useFirst)
            $this->restart_url = [];
        else 
            $this->restart_url2 = [];
    }

    /**
     * 작업 정보 prit 함수
     */
    private function printResultInfo(): void
    {
        echo "success count : {$this->success_count}\n";
        echo "fail count : {$this->fail_count}\n";
        echo "already count : {$this->already_count}\n";
        echo "dup count : {$this->dup_count}\n";
        echo "all count : {$this->all_count}\n";
        echo "all - dup count : ".$this->all_count - $this->dup_count."\n";
    }

    /**
     * 소요 시간 계산 함수
     */
    private function calculateElapseTime(): string
    {
        // 완료 시간 기록
        $this->endTime = new DateTime();
        $diff = $this->startTime->diff($this->endTime);
        return $diff->format("%H:%I:%S");
    }

    /**
     * http로 시작하는 URL인지 확인
     */
    private function checkURL(string $url): bool
    {
        return mb_substr($url,0,2) === "//";
    }
}