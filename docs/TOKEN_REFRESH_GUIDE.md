# دليل تجديد الـ Token التلقائي (Auto Token Refresh)

## نظرة عامة

يستخدم نظام HR_API بروتوكول OAuth2 عبر Laravel Passport للمصادقة. هذا الدليل يشرح كيفية التعامل مع الـ Tokens في التطبيقات العميلة.

---

## إعدادات صلاحية الـ Tokens

| النوع             | المدة    | الوصف                           |
| ----------------- | -------- | ------------------------------- |
| **Access Token**  | 15 دقيقة | يُستخدم لكل طلب API             |
| **Refresh Token** | 60 دقيقة | يُستخدم لتجديد الـ Access Token |

---

## مخطط دورة حياة الـ Token

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Token Lifecycle                             │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  1. LOGIN ──────► access_token (15 min) + refresh_token (60 min)   │
│                                                                     │
│  2. API REQUEST ──► Send access_token in Authorization Header      │
│                                                                     │
│  3. BEFORE EXPIRY ──► Use refresh_token to get new access_token    │
│                                                                     │
│  4. REFRESH EXPIRED ──► Redirect to Login                          │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 1. استجابة تسجيل الدخول

### Endpoint

```
POST /api/login
```

### Response

```json
{
    "status": true,
    "message": "Login successful",
    "data": {
        "token_type": "Bearer",
        "expires_in": 900,
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "refresh_token": "def50200a5b9f8c7..."
    }
}
```

> **ملاحظة:** `expires_in` = 900 ثانية = 15 دقيقة

---

## 2. تخزين الـ Tokens

```javascript
import AsyncStorage from "@react-native-async-storage/async-storage";

const TokenStorage = {
    // حفظ الـ Tokens
    saveTokens: async (accessToken, refreshToken, expiresIn) => {
        const expiryTime = Date.now() + expiresIn * 1000;

        await AsyncStorage.multiSet([
            ["access_token", accessToken],
            ["refresh_token", refreshToken],
            ["token_expiry", expiryTime.toString()],
        ]);
    },

    // جلب الـ Access Token
    getAccessToken: async () => {
        return await AsyncStorage.getItem("access_token");
    },

    // جلب الـ Refresh Token
    getRefreshToken: async () => {
        return await AsyncStorage.getItem("refresh_token");
    },

    // جلب وقت انتهاء الصلاحية
    getTokenExpiry: async () => {
        const expiry = await AsyncStorage.getItem("token_expiry");
        return expiry ? parseInt(expiry) : null;
    },

    // مسح جميع الـ Tokens
    clearTokens: async () => {
        await AsyncStorage.multiRemove([
            "access_token",
            "refresh_token",
            "token_expiry",
        ]);
    },

    // التحقق من صلاحية الـ Token
    isTokenValid: async () => {
        const expiry = await TokenStorage.getTokenExpiry();
        return expiry ? Date.now() < expiry : false;
    },

    // التحقق من قرب انتهاء الصلاحية (أقل من دقيقتين)
    isTokenExpiringSoon: async () => {
        const expiry = await TokenStorage.getTokenExpiry();
        if (!expiry) return true;
        const twoMinutesFromNow = Date.now() + 2 * 60 * 1000;
        return twoMinutesFromNow >= expiry;
    },
};

export default TokenStorage;
```

---

## 3. طلب تجديد الـ Token

### Endpoint

```
POST /oauth/token
```

### Request Body

```json
{
    "grant_type": "refresh_token",
    "refresh_token": "def50200a5b9f8c7...",
    "client_id": "2",
    "client_secret": "your-client-secret"
}
```

### Success Response (200)

```json
{
    "token_type": "Bearer",
    "expires_in": 900,
    "access_token": "NEW_ACCESS_TOKEN...",
    "refresh_token": "NEW_REFRESH_TOKEN..."
}
```

### Error Response (401)

```json
{
    "error": "invalid_grant",
    "error_description": "The refresh token is invalid.",
    "message": "The refresh token is invalid."
}
```

---

## 4. API Client مع التجديد التلقائي (Axios)

```javascript
import axios from "axios";
import TokenStorage from "./TokenStorage";

const BASE_URL = "https://your-api-domain.com/api";
const CLIENT_ID = "2";
const CLIENT_SECRET = "your-client-secret";

// إنشاء instance من Axios
const api = axios.create({
    baseURL: BASE_URL,
    timeout: 30000,
});

// Request Interceptor - إضافة الـ Token لكل طلب
api.interceptors.request.use(
    async (config) => {
        // تحقق من قرب انتهاء الصلاحية وجدد إذا لزم الأمر
        if (await TokenStorage.isTokenExpiringSoon()) {
            await refreshToken();
        }

        const token = await TokenStorage.getAccessToken();
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        return config;
    },
    (error) => Promise.reject(error)
);

// Response Interceptor - التعامل مع خطأ 401
api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;

        // تجنب الحلقة اللانهائية
        if (error.response?.status === 401 && !originalRequest._retry) {
            originalRequest._retry = true;

            try {
                await refreshToken();

                const newToken = await TokenStorage.getAccessToken();
                originalRequest.headers.Authorization = `Bearer ${newToken}`;

                return api(originalRequest);
            } catch (refreshError) {
                // فشل التجديد - توجيه للتسجيل
                await TokenStorage.clearTokens();
                // navigation.navigate('Login');
                return Promise.reject(refreshError);
            }
        }

        return Promise.reject(error);
    }
);

// دالة تجديد الـ Token
const refreshToken = async () => {
    const currentRefreshToken = await TokenStorage.getRefreshToken();

    if (!currentRefreshToken) {
        throw new Error("No refresh token available");
    }

    const response = await axios.post(`${BASE_URL}/../oauth/token`, {
        grant_type: "refresh_token",
        refresh_token: currentRefreshToken,
        client_id: CLIENT_ID,
        client_secret: CLIENT_SECRET,
    });

    const { access_token, refresh_token, expires_in } = response.data;

    await TokenStorage.saveTokens(access_token, refresh_token, expires_in);

    return access_token;
};

export default api;
```

---

## 5. سيناريوهات التعامل

| السيناريو               | الفحص                          | الإجراء                            |
| ----------------------- | ------------------------------ | ---------------------------------- |
| Token صالح              | `isTokenValid() = true`        | استخدمه مباشرة                     |
| Token قارب على الانتهاء | `isTokenExpiringSoon() = true` | جدده قبل الطلب                     |
| Token انتهى             | Response `401`                 | جدده وأعد الطلب                    |
| Refresh Token انتهى     | Refresh يُرجع `401`            | امسح البيانات وأعد التوجيه للتسجيل |

---

## 6. أفضل الممارسات

### ✅ يجب فعله

1. **التجديد الاستباقي** - جدد الـ Token قبل انتهائه بدقيقتين
2. **التخزين الآمن** - استخدم Secure Storage
3. **التعامل مع الأخطاء** - تأكد من وجود fallback لتسجيل الدخول
4. **تجنب الحلقات اللانهائية** - استخدم flag مثل `_retry`

### ❌ يجب تجنبه

1. **تخزين الـ Tokens في الذاكرة فقط**
2. **إرسال الـ Refresh Token في كل طلب**
3. **تجاهل أخطاء التجديد**

---

## 7. استكشاف الأخطاء

| الخطأ            | السبب المحتمل         | الحل               |
| ---------------- | --------------------- | ------------------ |
| `invalid_grant`  | Refresh Token منتهي   | إعادة تسجيل الدخول |
| `invalid_client` | Client ID/Secret خاطئ | تحقق من الإعدادات  |
| Network Error    | مشكلة اتصال           | تحقق من الشبكة     |

---

**آخر تحديث:** 2026-01-13
