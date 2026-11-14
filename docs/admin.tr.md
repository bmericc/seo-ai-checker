🇹🇷 Türkçe | [🇬🇧 English](admin.md)

[← README'ye dön](../README.tr.md)

# Kullanıcı yönetimi ve yönetim paneli

- Her Google hesabı giriş yapıp bir `User` kaydı oluşturabilir. `approved_at`
  varsayılan olarak boştur (onay bekliyor) ve onaylanana kadar panele
  erişilemez.
- `ADMIN_EMAILS` listesindeki (`.env`/`.env.prod`) e-postalar ilk girişte
  otomatik olarak `is_admin=true` ve onaylı olur (ilk kurulumda kendinize
  erişim açmak için).
- Yöneticiler `/admin/users` sayfasından (menüdeki "Kullanıcılar" linki)
  diğer kullanıcıları onaylayabilir, onayını kaldırabilir, yönetici
  yapabilir/yöneticilikten alabilir veya silebilir. Bir yönetici bu
  işlemleri kendi hesabına uygulayamaz (yanlışlıkla kendini dışarıda
  bırakmasın diye). `ADMIN_EMAILS` hesapları için bu zaten sorun değildir;
  her girişte yeniden yönetici ve onaylı yapılırlar.

![Kullanıcı yönetimi](images/screenshots/tr-admin-users.png)
