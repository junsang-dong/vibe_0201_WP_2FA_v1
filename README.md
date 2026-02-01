# AI NEXT 2FA

워드프레스 로그인 보안을 강화하기 위한 2FA 플러그인 스켈레톤입니다.

## 개발 정보

- 개발자: Junsang Dong
- 개발사: AINext
- 연락처: naebon@naver.com

## 주요 작업 내용

- TOTP 기반 2단계 인증 플로우 구현 및 2FA 전용 로그인 화면 제공
- 사용자 프로필에서 2FA 활성화/재설정 및 TOTP 시크릿 표시
- 로그인 화면 reCAPTCHA v2 체크박스 연동(키 입력 시 서버 검증)
- 관리자 설정 페이지 추가(2FA 강제, reCAPTCHA, XML-RPC 차단, WooCommerce/유출 암호 옵션 스켈레톤)
- 로그인 시도 실패 레이트리밋 스켈레톤 추가

## 기능 요약

- TOTP 기반 2FA(6자리 코드)
- Google Authenticator 등록 지원(otpauth URI 제공)
- 로그인 reCAPTCHA v2 체크박스
- XML-RPC 로그인 차단 옵션
- WooCommerce/유출 암호 차단 옵션(스켈레톤)

## 적용 방법

1) 플러그인 폴더 ZIP 생성
- `wp-content/plugins/vibe-2fa/` 폴더를 `vibe-2fa.zip`으로 압축

2) 워드프레스 관리자에서 업로드/활성화
- 관리자 → 플러그인 → 새로 추가 → 플러그인 업로드 → ZIP 선택 → 설치 → 활성화

3) 관리자 설정
- 관리자 → 설정 → AI NEXT 2FA
- reCAPTCHA 사용 시 Site Key/Secret Key 입력 후 저장

4) 사용자 2FA 등록
- 관리자 → 사용자 → 프로필
- “2FA 사용” 체크 후 저장
- TOTP 시크릿 또는 QR 코드 URI를 Google Authenticator에 등록

## 참고

- reCAPTCHA 키가 비어 있으면 임의 텍스트 입력으로 통과되도록 구성됨
- 운영 환경 적용 전 백업/테스트 권장
