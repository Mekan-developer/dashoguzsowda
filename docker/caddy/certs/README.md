# SSL-сертификаты

Сюда положить на сервере собственные сертификаты домена:

```
fullchain.pem   сертификат домена + промежуточные сертификаты CA (в одном файле)
privkey.pem     приватный ключ, без пароля
```

Если CA выдал файлы отдельно (`domain.crt`, `intermediate.crt` / `ca-bundle.crt`),
цепочку нужно собрать — порядок важен, сертификат домена первым:

```bash
cat domain.crt intermediate.crt > fullchain.pem
```

Если ключ в формате PKCS#8 с паролем или .pfx — сконвертировать:

```bash
# из .pfx
openssl pkcs12 -in cert.pfx -clcerts -nokeys -out fullchain.pem
openssl pkcs12 -in cert.pfx -nocerts -nodes -out privkey.pem

# снять пароль с ключа
openssl rsa -in privkey-encrypted.pem -out privkey.pem
```

Права:

```bash
chmod 644 fullchain.pem
chmod 600 privkey.pem
```

Проверка соответствия ключа и сертификата (хэши должны совпасть):

```bash
openssl x509 -noout -modulus -in fullchain.pem | openssl md5
openssl rsa  -noout -modulus -in privkey.pem   | openssl md5
```

Пути внутри контейнера задаются через `TLS_CERT` / `TLS_KEY` в `.env`.
