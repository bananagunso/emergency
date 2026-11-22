
#include <ESP8266WiFi.h>
#include <WiFiClientSecure.h>
#include <ESP8266HTTPClient.h>

// --------------------------
// Wi-Fi設定
// --------------------------
const char* WIFI_SSID = "JCOM_BCWL";
const char* WIFI_PASSWORD = "304265353114";

// CH3のアクセスポイントを指定
uint8_t bssid[] = {
  0x00, 0xEB, 0xD8, 0x09, 0x99, 0x81
};

// --------------------------
// サーバー設定
// --------------------------
const char* API_URL =
  "https://bananagunso-dev.f5.si/emergency/api/call.php";

const char* API_TOKEN = "3f577b6ada3195d981522245086b57e5b0baf59cf9cc6c766bd9f9ae01179840";

const char* SENDER = "bedroom";

// --------------------------
// ボタン設定
// --------------------------
#define BUTTON_PIN D2

const unsigned long DEBOUNCE_TIME = 30;

int lastReading = HIGH;
int buttonState = HIGH;
unsigned long lastDebounceTime = 0;
unsigned long lastWifiAttempt = 0;

// --------------------------
// Wi-Fi接続
// --------------------------
void connectWiFi() {
  WiFi.mode(WIFI_STA);

  Serial.println("Connecting to WiFi...");

  WiFi.begin(
    WIFI_SSID,
    WIFI_PASSWORD,
    3,
    bssid
  );

  unsigned long start = millis();

  while (WiFi.status() != WL_CONNECTED &&
         millis() - start < 20000) {
    delay(500);
    Serial.print(".");
  }

  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("WiFi connected");
    Serial.print("IP: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.print("WiFi failed, status=");
    Serial.println(WiFi.status());
  }

  lastWifiAttempt = millis();
}

// --------------------------
// 呼び出しAPI送信
// --------------------------
void sendCall() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi unavailable. Call not sent.");
    return;
  }

  WiFiClientSecure client;

  // 初期動作確認用
  client.setInsecure();

  HTTPClient http;
  http.setTimeout(5000);

  if (!http.begin(client, API_URL)) {
    Serial.println("HTTP begin failed");
    return;
  }

  http.addHeader(
    "Authorization",
    String("Bearer ") + API_TOKEN
  );

  http.addHeader(
    "Content-Type",
    "application/json"
  );

  String payload =
    String("{\"sender\":\"") + SENDER + "\"}";

  Serial.println("Sending call...");

  int httpCode = http.POST(payload);

  if (httpCode > 0) {
    Serial.print("HTTP Status: ");
    Serial.println(httpCode);

    Serial.println(http.getString());

    if (httpCode == HTTP_CODE_OK) {
      Serial.println("CALL SENT OK");
    } else {
      Serial.println("CALL FAILED");
    }
  } else {
    Serial.print("HTTP POST failed: ");
    Serial.println(http.errorToString(httpCode));
  }

  http.end();
}

// --------------------------
// ボタン監視
// --------------------------
void checkButton() {
  int reading = digitalRead(BUTTON_PIN);

  if (reading != lastReading) {
    lastDebounceTime = millis();
  }

  if (millis() - lastDebounceTime >= DEBOUNCE_TIME) {
    if (reading != buttonState) {
      buttonState = reading;

      // HIGHからLOWになった瞬間だけ送信
      if (buttonState == LOW) {
        Serial.println("BUTTON PRESSED");
        sendCall();
      }
    }
  }

  lastReading = reading;
}

// --------------------------
// 初期化
// --------------------------
void setup() {
  Serial.begin(115200);

  pinMode(BUTTON_PIN, INPUT_PULLUP);

  lastReading = digitalRead(BUTTON_PIN);
  buttonState = lastReading;

  Serial.println();
  Serial.println("Emergency Sender Started");

  connectWiFi();
}

// --------------------------
// メインループ
// --------------------------
void loop() {
  checkButton();

  // Wi-Fi切断時は10秒間隔で再接続
  if (WiFi.status() != WL_CONNECTED &&
      millis() - lastWifiAttempt >= 10000) {
    connectWiFi();
  }

  delay(5);
}
