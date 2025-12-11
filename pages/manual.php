<div class="manual-container" style="max-width: 1000px; margin: 0 auto; padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid #007bff; padding-bottom: 10px;">
        <h2 style="margin: 0; border: none; padding: 0;">CRM 사용설명서</h2>
        <button type="button" class="btn btn-secondary" onclick="history.back()">뒤로가기</button>
    </div>

    <div class="manual-nav" style="margin-bottom: 30px; background: #f8f9fa; padding: 15px; border-radius: 5px;">
        <strong>목차:</strong>
        <a href="#section-intranet" style="margin-left: 10px;">인트라넷</a> |
        <a href="#section-customer" style="margin-left: 10px;">고객관리</a> |
        <a href="#section-contract" style="margin-left: 10px;">계약관리</a> |
        <a href="#section-collection" style="margin-left: 10px;">회수관리</a> |
        <a href="#section-transaction" style="margin-left: 10px;">입출금관리</a> |
        <a href="#section-reports" style="margin-left: 10px;">보고서</a> |
        <a href="#section-sms" style="margin-left: 10px;">SMS관리</a> |
        <a href="#section-admin" style="margin-left: 10px; color: red;">관리자메뉴</a>
    </div>

    <div id="section-intranet" class="manual-section" style="margin-bottom: 40px;">
        <h3 style="color: #0d6efd;">1. 인트라넷</h3>
        <p>로그인 후 첫 화면으로, 사내 커뮤니케이션과 회사 정보를 확인하는 곳입니다.</p>
        <details class="manual-details">
            <summary style="cursor: pointer; font-weight: bold; margin-bottom: 10px;">상세 사용법 보기</summary>
            <ul>
                <li><strong>회사 정보 확인:</strong> 상단 표에서 [사업자등록번호], [대부업등록번호], [이자/경비 계좌] 등을 확인할 수 있습니다. 클릭 시 관련 이미지가 새 창으로 열립니다.</li>
                <li><strong>내부 메시지 발송:</strong>
                    <ul>
                        <li>왼쪽 '내부 메시지 보내기' 폼에서 [받는 사람]을 선택하고 내용을 입력하여 전송합니다.</li>
                        <li><strong>관련 계약 첨부:</strong> 특정 계약과 관련된 문의라면 [참고 계약] 드롭다운에서 계약을 선택하여 함께 보낼 수 있습니다. 수신자는 해당 계약으로 바로 이동할 수 있습니다.</li>
                    </ul>
                </li>
                <li><strong>메시지 확인 및 답장:</strong>
                    <ul>
                        <li>오른쪽 탭에서 [받은 메시지]와 [보낸 메시지]를 확인할 수 있습니다.</li>
                        <li>메시지를 클릭하면 팝업으로 상세 내용이 표시되며, 바로 [답장하기]가 가능합니다.</li>
                        <li>[읽지 않은 메시지만 보기] 체크박스를 통해 안 읽은 메시지만 필터링할 수 있습니다.</li>
                    </ul>
                </li>
            </ul>
        </details>
    </div>

    <div id="section-customer" class="manual-section" style="margin-bottom: 40px;">
        <h3 style="color: #0d6efd;">2. 고객관리</h3>
        <p>고객 정보 등록 및 수정, 관리를 수행합니다.</p>
        <details class="manual-details">
            <summary style="cursor: pointer; font-weight: bold; margin-bottom: 10px;">상세 사용법 보기</summary>
            <ul>
                <li><strong>신규 고객 등록:</strong>
                    <ul>
                        <li>상단 검색창 우측의 <strong>[고객 등록]</strong> 버튼을 클릭합니다.</li>
                        <li>고객명, 주민번호, 연락처, 은행정보, 주소, 담당자 등을 입력 후 [저장]합니다. (주민번호는 앞6자리-뒤1자리 형식 준수)</li>
                    </ul>
                </li>
                <li><strong>검색 및 조회:</strong>
                    <ul>
                        <li>이름이나 연락처로 고객을 검색할 수 있습니다.</li>
                        <li>고객 목록에서 [이름]을 클릭하면 <strong>고객 상세 페이지</strong>로 이동하며, 해당 고객의 모든 계약 및 메모 내역을 볼 수 있습니다.</li>
                    </ul>
                </li>
                <li><strong>수정 및 삭제:</strong> 목록 우측의 [수정] 버튼으로 정보를 변경하거나, 관리자 권한으로 [삭제]할 수 있습니다.</li>
            </ul>
        </details>
    </div>

    <div id="section-contract" class="manual-section" style="margin-bottom: 40px;">
        <h3 style="color: #0d6efd;">3. 계약관리</h3>
        <p>대출 계약을 생성하고, 계약의 전반적인 상태를 관리합니다.</p>
        <details class="manual-details">
            <summary style="cursor: pointer; font-weight: bold; margin-bottom: 10px;">상세 사용법 보기</summary>
            <ul>
                <li><strong>신규 계약 추가:</strong>
                    <ul>
                        <li>[신규 계약 추가] 버튼을 클릭하면 등록 폼이 나타납니다.</li>
                        <li><strong>기존 고객 검색:</strong> 계약할 고객을 검색하여 선택합니다.</li>
                        <li><strong>조건 입력:</strong> 상품명, 대출금액, 대출일/만기일, 이자율 등을 입력합니다. 이자율 입력 시 연체이자율은 법정 최고금리(20%) 내에서 자동 계산됩니다.</li>
                        <li>[유예약정금]이 있다면 별도 입력합니다.</li>
                        <li>저장 시 자동으로 고유 <strong>계약번호</strong>가 생성됩니다.</li>
                    </ul>
                </li>
                <li><strong>계약 목록 및 상태:</strong>
                    <ul>
                        <li>[정상], [연체], [부실] 등 상태별로 필터링하여 볼 수 있습니다.</li>
                        <li>배경색 구분: <span style="background-color: #f3fa97ff;">노란색</span>은 약정일 당일, <span style="background-color: #f8d7da;">빨간색</span>은 연체 중인 계약입니다.</li>
                        <li>[구분코드]: 계약에 '소송진행', '파산' 등의 특이사항 태그를 [구분코드 일괄 적용]으로 붙일 수 있습니다.</li>
                    </ul>
                </li>
                <li><strong>주요 기능 버튼:</strong>
                    <ul>
                        <li>[예상이자]: 특정 일자 기준 상환해야 할 총 금액을 계산해주는 팝업을 엽니다.</li>
                        <li>[원장]: 해당 계약의 전체 입출금 내역(Transaction Ledger)을 봅니다.</li>
                        <li>[SMS]: 해당 계약 차주에게 문자를 발송합니다.</li>
                        <li>[입금]: 일반 권한 사용자가 개별 입금을 등록할 때 사용합니다.</li>
                    </ul>
                </li>
            </ul>
        </details>
    </div>

    <div id="section-collection" class="manual-section" style="margin-bottom: 40px;">
        <h3 style="color: #0d6efd;">4. 회수관리</h3>
        <p>고객으로부터 입금된 금액을 등록하고 처리하는 메뉴입니다.</p>
        <details class="manual-details">
            <summary style="cursor: pointer; font-weight: bold; margin-bottom: 10px;">상세 사용법 보기</summary>
            <ul>
                <li><strong>자동 분개 입금 처리:</strong>
                    <ul>
                        <li>계약을 선택하고 [회수일]과 [총 회수액]을 입력 후 <strong>[계산하기]</strong>를 누릅니다.</li>
                        <li>시스템이 경비 -> 연체이자 -> 정상조정이자 -> 부족금 -> 원금 순서로 자동 계산하여 배분 내역을 보여줍니다.</li>
                        <li>내역 확인 후 [최종 저장]을 누르면 입금이 반영됩니다.</li>
                        <li>*참고: 입금일은 마지막 입금일 이후 날짜만 선택 가능합니다.</li>
                    </ul>
                </li>
                <li><strong>일괄 업로드 (엑셀/CSV):</strong>
                    <ul>
                        <li>입금 내역이 많을 경우 CSV 파일로 한 번에 업로드할 수 있습니다. 샘플 파일을 다운로드하여 양식에 맞춰 작성하세요.</li>
                        <li>[수기계산 업로드] 기능을 통해 시스템 자동 계산을 따르지 않고 강제로 이자/원금을 지정하여 입력할 수도 있습니다(과거 데이터 보정용).</li>
                    </ul>
                </li>
                <li><strong>입금 내역 수정/삭제:</strong>
                    <ul>
                        <li>등록된 입금 내역은 리스트에서 확인 가능합니다.</li>
                        <li>잘못 등록된 경우 [삭제]할 수 있으나, 데이터 정합성을 위해 <strong>가장 최근(마지막) 입금 내역부터 순차적으로만 삭제</strong> 가능합니다.</li>
                    </ul>
                </li>
            </ul>
        </details>
    </div>

    <div id="section-transaction" class="manual-section" style="margin-bottom: 40px;">
        <h3 style="color: #0d6efd;">5. 입출금관리</h3>
        <p>전체적인 자금의 흐름을 파악하고 엑셀로 백업하는 메뉴입니다.</p>
        <details class="manual-details">
            <summary style="cursor: pointer; font-weight: bold; margin-bottom: 10px;">상세 사용법 보기</summary>
            <ul>
                <li><strong>출금 명세 (대출 실행):</strong> 기간별 대출금 지급 내역을 조회합니다.</li>
                <li><strong>입금 명세 (상환 내역):</strong> 고객별, 기간별 상환 내역을 조회합니다.</li>
                <li><strong>엑셀 다운로드:</strong> 조회된 내역을 엑셀 파일로 내려받아 별도 보관하거나 2차 가공할 수 있습니다.</li>
            </ul>
        </details>
    </div>

    <div id="section-reports" class="manual-section" style="margin-bottom: 40px;">
        <h3 style="color: #0d6efd;">6. 업무일보 (보고서)</h3>
        <p>경영 현황을 한눈에 파악할 수 있는 통계 페이지입니다.</p>
        <details class="manual-details">
            <summary style="cursor: pointer; font-weight: bold; margin-bottom: 10px;">상세 사용법 보기</summary>
            <ul>
                <li><strong>채권 통계 (Snapshot):</strong>
                    <ul>
                        <li>지정한 기준일의 전체 대출 잔액과 연체 현황을 보여줍니다.</li>
                        <li>정상채권 vs 연체채권 비율, 연체 기간별(30일/60일/90일 등) 채권 분포를 확인할 수 있습니다.</li>
                    </ul>
                </li>
                <li><strong>자금 현황 (Period):</strong>
                    <ul>
                        <li>지정한 기간(`자금 시작일` ~ `자금 종료일`) 동안의 실제 입출금 총액을 보여줍니다.</li>
                        <li>회수된 원금, 이자 수익, 경비 수입 등을 분류하여 표시합니다.</li>
                    </ul>
                </li>
            </ul>
        </details>
    </div>

    <div id="section-sms" class="manual-section" style="margin-bottom: 40px;">
        <h3 style="color: #0d6efd;">7. SMS 관리</h3>
        <p>고객 안내 문자 발송 및 템플릿 관리를 수행합니다.</p>
        <details class="manual-details">
            <summary style="cursor: pointer; font-weight: bold; margin-bottom: 10px;">상세 사용법 보기</summary>
            <ul>
                <li><strong>발송 대상 선택 (필터):</strong>
                    <ul>
                        <li>[약정일] 필터를 통해 5일, 10일 등 특정 결제일 고객만 추려낼 수 있습니다.</li>
                        <li>배경색: <span style="background-color: #ffffcc;">노란색</span>(당일약정), <span style="background-color: #ffe5cc;">주황색</span>(3일이내) 등으로 시각적 구분이 됩니다.</li>
                    </ul>
                </li>
                <li><strong>템플릿 사용:</strong>
                    <ul>
                        <li>미리 등록된 템플릿을 선택하면 메시지가 자동 완성됩니다.</li>
                        <li><strong>변수 기능:</strong> `[고객명]`, `[현재대출잔액]`, `[오늘총이자]` 등의 변수를 템플릿에 넣으면, 각 고객의 실제 데이터로 자동 치환되어 발송됩니다.</li>
                    </ul>
                </li>
                <li><strong>미래일자 계산 발송:</strong>
                    <ul>
                        <li>[미래일자] 입력칸에 날짜를 지정하면, 오늘이 아닌 해당 날짜 기준의 이자와 완납금액을 계산하여 문자에 넣을 수 있습니다(`[미래일자총이자]` 변수 사용).</li>
                    </ul>
                </li>
            </ul>
        </details>
    </div>

    <div id="section-admin" class="manual-section" style="margin-bottom: 40px; border-top: 2px dashed #dc3545; padding-top: 20px;">
        <h3 style="color: #dc3545;">8. 관리자 메뉴 (Admin Only)</h3>
        <p>최고관리자 권한(Level 0)을 가진 계정에게만 보이는 특수 기능입니다. 사이드바의 [관리자메뉴]를 클릭하여 접근합니다.</p>
        <details class="manual-details" open>
            <summary style="cursor: pointer; font-weight: bold; margin-bottom: 10px;">상세 사용법 보기</summary>
            <ul>
                <li><strong>직원 관리:</strong>
                    <ul>
                        <li>새로운 직원의 아이디/비밀번호를 생성하거나, 기존 직원의 정보를 수정/삭제합니다.</li>
                        <li><strong>권한 레벨:</strong> '최고관리자(0)'는 모든 메뉴 접근 가능, '일반직원(1)'은 관리자 메뉴 접근 불가 및 일부 삭제 기능이 제한됩니다.</li>
                    </ul>
                </li>
                <li><strong>시스템 설정:</strong>
                    <ul>
                        <li><strong>회사 정보:</strong> 사업자번호, 대표번호, 입금계좌 정보를 수정하면 인트라넷 및 문자 발송 시 자동 반영됩니다.</li>
                        <li><strong>SMS API:</strong> 문자 발송을 위한 API 키와 발신번호를 설정합니다.</li>
                        <li><strong>자주 쓰는 메모:</strong> 고객 상담 시 자주 사용하는 문구를 등록하여 상담 효율을 높입니다.</li>
                        <li><strong>구분코드 관리:</strong> 계약 관리에 사용되는 태그(예: 파산, 회생 등)를 추가/삭제합니다.</li>
                    </ul>
                </li>
                <li><strong>휴일 관리:</strong>
                    <ul>
                        <li>달력에서 특정 날짜를 클릭하여 휴일(빨간색)로 지정하거나 해제합니다.</li>
                        <li>지정된 휴일은 연체일수 계산이나 약정일 산정 로직에 영향을 줄 수 있습니다.</li>
                    </ul>
                </li>
                <li><strong>회사 관련 이미지:</strong> 로그인 화면의 로고, 인트라넷의 사업자등록증 이미지 등을 업로드합니다.</li>
                <li><strong>데이터베이스 관리:</strong> 만약의 사태를 대비해 DB 전체를 백업하거나 복원할 수 있습니다.</li>
            </ul>
        </details>
    </div>

    <div style="margin-top: 50px; text-align: center; color: #6c757d;">
        <p>추가적인 문의사항은 관리자에게 문의바랍니다.</p>
    </div>
</div>

<?php include 'footer.php'; ?>