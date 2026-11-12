# Sitem Google'da ve Yapay Zekâda Nerede?

Bir süredir şirket sitelerimizin Google'daki durumunu takip ederken aynı sorunla karşılaşıyordum. Bir anahtar kelimede kaçıncı sırada olduğumuzu biliyorduk ama ChatGPT'ye veya Gemini'ye aynı konuyla ilgili bir soru sorulduğunda hangi sitelerin önerildiğini bilmiyorduk.

SEO tarafında alıştığımız ölçümler hâlâ önemli. Google'da kaçıncı sırada çıktığımız, ne kadar trafik aldığımız, hangi kelimelerin ziyaretçi getirdiği gibi verileri takip etmeye devam ediyoruz. Ancak kullanıcıların bilgiye ulaşma biçimi değişiyor. Artık bir ürün araştırırken arama motoru yerine doğrudan yapay zekâ asistanına danışanlar da var.

Google'ın AI Overview özelliği de işin başka bir boyutu. Organik sonuçlarda üst sıralarda olmak, AI Overview yanıtında kaynak olarak gösterileceğiniz anlamına gelmiyor.

Ben de şirket projelerimizin sitelerini incelerken bütün bu verileri tek bir yerde görmek istedim.

Google sıralaması için ayrı bir araç, Lighthouse için başka bir ekran, Search Console, Analytics ve yapay zekâ asistanlarında manuel kontroller derken işler gereğinden fazla dağılmıştı.

Bunları bir araya getirmek için **SEO / AI Overview Checker** adını verdiğim bir uygulama geliştirdim.

- **Kaynak kodları:** https://github.com/bmericc/seo-ai-checker
- **Demo:** https://seo-checker.tarti.com/

## Neden açık kaynak olarak yayımladım?

Aslında başlangıçta projeyi yayımlamak gibi bir düşüncem yoktu. Kendi kullandığımız sitelerin durumunu takip etmek için yazdığım bir araçtı.

Geliştirme sırasında GitHub'daki açık kaynak SEO projelerini epey inceledim. Özellikle Google sonuçlarının nasıl ayrıştırıldığı, AI Overview tespitinin nasıl yapıldığı, robots.txt kontrolleri ve teknik SEO denetimleri konusunda bu projelerden yararlandım.

Bir noktadan sonra kullandığım yöntemlerin ve geliştirdiğim özelliklerin başkalarının da işine yarayabileceğini düşündüm.

Bu nedenle projeyi **GPL v3 lisansıyla** GitHub'da yayımladım.

## Google sıralaması ve AI Overview takibi

Uygulamanın ilk geliştirdiğim bölümü anahtar kelime takibiydi.

Belirlediğim kelimeler için Google'ın ilk yaklaşık 20 organik sonucunu kontrol ediyor ve takip ettiğim alan adının kaçıncı sırada olduğunu kaydediyor. Böylece bir kelimede zaman içinde yükselme veya düşme olup olmadığını görebiliyorum.

Varsayılan olarak doğrudan HTTP istekleriyle çalışıyor. Ancak Google'ın otomatik sorgulara karşı uyguladığı kısıtlamalar nedeniyle bu yöntem her zaman sonuç vermeyebiliyor. Daha güvenilir veri almak isteyenler DataForSEO API'sini kullanabiliyor.

İkinci konu ise AI Overview.

Bir sorguda Google'ın yapay zekâ tarafından oluşturulan yanıtı bulunuyor mu? Bulunuyorsa hangi siteler kaynak gösteriliyor? Takip ettiğimiz alan adı bunların arasında mı?

Uygulama bunları da kaydediyor.

Burada bir sınırlama var. Google, AI Overview sonuçlarını sorguya, konuma ve kullanıcıya göre değiştirebiliyor. Ayrıca içerik zaman zaman JavaScript üzerinden oluşturuluyor. Dolayısıyla özellikle doğrudan HTTP yöntemiyle yapılan kontrollerin her AI Overview sonucunu yakalayacağını söylemek mümkün değil.

Ben bu veriyi kesin bir sıralama ölçümünden çok, zaman içinde takip edilebilecek ek bir gösterge olarak değerlendiriyorum. (İlerleyen süreçte bu alandaki dalgalanmaları ve rakip paylarını daha net görebilmek adına **AI Overview Volatility** ve **Rakip Dominasyon Matrisi** gibi metrikleri de sisteme entegre etmeyi planlıyorum.)

## ChatGPT, Claude, Gemini ve Perplexity bizi öneriyor mu?

Projeyi geliştirmemin asıl nedenlerinden biri buydu.

Örneğin bir kullanıcıya ürün satıyorsanız, yalnızca ürün adında Google'da kaçıncı olduğunuzla ilgilenmiyorsunuz. Kullanıcının bir yapay zekâ asistanına sorduğu soruda hangi markaların ve sitelerin önerildiğini de bilmek istiyorsunuz.

Uygulamada anahtar kelimelerle ilişkili, gerçek kullanıcıların sorabileceği sorular tanımlanabiliyor.

Bu sorular ChatGPT, Claude, Gemini ve Perplexity üzerinden kontrol ediliyor.

Yanıtlarda takip ettiğimiz site geçiyor mu, kaynak olarak gösteriliyor mu, başka hangi siteler öneriliyor gibi bilgileri kaydediyorum.

Ayrıca **Prompt Explorer** adında bir bölüm var. Burada istediğim soruyu dört asistana yöneltip yanıtlarını karşılaştırabiliyorum.

Elbette bu sonuçlar sabit değil. Aynı soru farklı zamanlarda, farklı model sürümlerinde veya farklı koşullarda başka yanıtlar üretebiliyor. Tek bir kontrol sonucundan bir sitenin bütün yapay zekâ sistemlerindeki görünürlüğünü çıkarmak doğru olmaz.

Bu yüzden benim için önemli olan yalnızca bir yanıtta önerilip önerilmediğimiz değil, aynı soruları düzenli olarak kontrol edip değişimi izleyebilmek.

## Teknik SEO kontrolleri ve AI Bot / llms.txt Analizi

Anahtar kelime takibinin yanında sitelerin teknik durumunu da kontrol etmek istedim.

Uygulama şu başlıklarda denetimler yapıyor:

- **robots.txt:** Arama motoru ve yapay zekâ botlarına (`GPTBot`, `ClaudeBot`, `PerplexityBot` vb.) yönelik erişim kuralları
- **Sitemap:** Site haritasının bulunması ve erişilebilirliği
- **HTTPS:** Yönlendirmeler ve güvenli bağlantı
- **Canonical:** Tercih edilen alan adı ve URL kullanımı
- **Güvenlik başlıkları:** HTTP yanıtlarında bulunması gereken başlıklar
- **WHOIS:** Alan adı kayıt bilgileri ve yaşı
- **llms.txt:** Dosyanın bulunup bulunmadığı ve LLM dostu yapılandırılıp yapılandırılmadığı

Özellikle yapay zekâ botlarıyla ilgili kontrolleri eklemek istedim. GPTBot, ClaudeBot ve benzeri botlar için tanımlanan kuralları görebiliyorum.

Ancak burada önemli bir ayrım var. Bir yapay zekâ sağlayıcısının eğitim amaçlı tarama botunu engellemek, o sağlayıcının bütün ürünlerinin siteye erişemeyeceği anlamına gelmiyor. Arama ve kullanıcı isteğine bağlı erişim için farklı botlar veya yöntemler kullanılabiliyor.

`llms.txt` de benzer şekilde dikkatli değerlendirilmesi gereken bir konu. Yapay zekâ sistemlerine site içeriğini tanıtmak amacıyla önerilen bir dosya biçimi olsa da henüz bütün sağlayıcıların benimsediği yerleşik bir standart değil. Eklenmesinin arama sıralamasını veya yapay zekâ yanıtlarında görünürlüğü artıracağına dair bir garanti yok.

Kontrollerin sonuçları bir sağlık skorunda toplanıyor. Tespit edilen sorunların yanında nasıl düzeltilebileceklerine ilişkin açıklamalar bulunuyor.

İki kontrol arasında değişen maddeleri de görebiliyorum. Özellikle bir sunucu veya site yapılandırmasını değiştirdikten sonra bu özellik işe yarıyor.

## Lighthouse ve sayfa içi SEO

Performans ölçümü için PageSpeed Insights API'sini kullanıyorum. Böylece uygulamanın çalıştığı sunucuda ayrıca Chrome veya Lighthouse çalıştırmam gerekmiyor.

Lighthouse sonuçlarının yanında, mevcut olduğunda Chrome User Experience Report (CrUX) verileri de görüntülenebiliyor.

Sayfa içi SEO tarafında ise başlıklar, meta açıklamaları, heading hiyerarşisi, görseller, structured data ve hreflang gibi öğeler kontrol ediliyor.

Bu kontrolleri tek başına bir SEO başarısı ölçütü olarak görmüyorum. Ancak özellikle bir site güncellemesinden sonra unutulan veya yanlış yapılandırılan öğeleri bulmak için kullanışlı.

## Search Console, Analytics ve Bing verileri

Uygulamanın yalnızca dışarıdan kontrol yapan bir araç olmasını istemedim.

Bu nedenle Google Search Console, Google Analytics 4 ve Bing Webmaster Tools bağlantılarını da ekledim.

Kullanıcılar kendi hesaplarını OAuth üzerinden bağlayabiliyor. Böylece arama performansı, ziyaretçi verileri ve ilgili servislerin sunduğu diğer bilgiler aynı panelden incelenebiliyor.

Özellikle Google'daki sıralama kontrolleriyle Search Console verilerini birlikte görmek işimi kolaylaştırıyor.

## IndexNow desteği

IndexNow desteği de eklediğim özelliklerden biri.

Yeni eklenen veya güncellenen URL'leri IndexNow destekleyen arama motorlarına bildirebiliyorum.

Uygulama gerekli anahtar dosyasını oluşturup doğrulayabiliyor. İstenirse tek tek URL'ler veya site haritasındaki adresler gönderilebiliyor.

Tabii IndexNow üzerinden bir URL bildirmek, o sayfanın mutlaka dizine ekleneceği anlamına gelmiyor. Yalnızca destekleyen arama motorlarına değişiklik hakkında bilgi verilmesini sağlıyor.

## MCP ile asistan üzerinden yönetim

Daha önce geliştirdiğim Cockpit MCP Gateway projesinden sonra burada da MCP desteği eklemek istedim.

**Model Context Protocol (MCP)** sayesinde uygulamanın bazı işlevlerine doğrudan bir yapay zekâ asistanından erişilebiliyor.

ChatGPT, Claude, Cursor veya Claude Code üzerinden bağlanıp alan adlarını incelemek, kontrol sonuçlarını almak ve yetki dahilinde yeni kontroller başlatmak mümkün.

Örneğin asistana şöyle bir soru sorabiliyorum:

> ornekmagaza.com için öncelikli SEO düzeltmelerini listele.

Asistan MCP üzerinden alan adının bilgilerini alıyor, tespit edilen sorunları inceliyor ve bir düzeltme planı hazırlayabiliyor.

Başka bir örnek:

> Hangi anahtar kelimelerde Google AI Overview gösteriliyor ama bizim sitemiz kaynaklar arasında bulunmuyor?

Normalde panelde ayrı ayrı inceleyeceğim sonuçları bu şekilde doğrudan sorabiliyorum.

### MCP bağlantısı ve yetkilendirme

MCP sunucusunda OAuth desteği bulunuyor.

Bağlantı sırasında tarayıcı üzerinden Google hesabıyla giriş yapılıyor ve erişim izni veriliyor. Ayrıca elle token oluşturup kopyalamak gerekmiyor.

İki yetki seviyesi var:

- `read`: Verileri okuma
- `write`: İzin verilen işlemleri gerçekleştirme ve kontrol başlatma

Kullanıcı yalnızca kendisine ait veya kendisiyle paylaşılmış alan adlarına erişebiliyor. MCP üzerinden silme işlemi sunulmuyor.

Claude Code için bağlantı komutu:

```bash
claude mcp add --transport http seo-ai-checker https://<adresiniz>/mcp
```

Depoda ayrıca site denetimi, anahtar kelime raporu, yapay zekâ görünürlük araştırması, müşteri raporu ve IndexNow işlemleri için hazırlanmış ajan becerileri (skills) bulunuyor.

### API anahtarı olmadan yapay zekâ kontrolü

MCP tarafında özellikle kullanışlı bulduğum bir özellik daha var.

Normalde yapay zekâ görünürlük kontrolü yapmak için ilgili sağlayıcının API'sine erişmek gerekiyor.

Ancak manuel AI kontrolünde, bağlı asistan kendisine verilen soruyu yanıtlayıp sonucu MCP üzerinden kaydedebiliyor.

Örneğin ChatGPT üzerinden bağlandığımda, uygulamadaki soruları ChatGPT'ye yanıtlatıp sonuçları kaydedebiliyorum.

Bu yöntemin otomatik API kontrolleriyle tamamen aynı koşullarda çalıştığını varsaymamak gerekiyor. Kullanılan model, oturum bağlamı ve erişilebilir araçlar sonucu etkileyebilir.

Yine de farklı asistanlarla doğrudan deneme yapmak için kullanışlı bir yöntem oldu.

## Kullanıcılar ve rapor paylaşımı

Uygulamada Google hesabıyla giriş yapılıyor. Kullanıcı kaydı davet ve yönetici onayıyla çalışıyor.

Her kullanıcı yalnızca kendi alan adlarını veya kendisiyle paylaşılanları görebiliyor.

Alan adları başka kullanıcılarla okuma veya yazma yetkisi verilerek paylaşılabiliyor.

Müşterilere veya ekip arkadaşlarına rapor göndermek için ayrıca giriş gerektirmeyen, salt okunur rapor bağlantıları oluşturdum.

Haftalık SEO özetleri de e-postayla gönderilebiliyor.

Arayüz Türkçe ve İngilizce. İlk açılışta tarayıcı dili dikkate alınıyor, istenirse ayarlardan değiştirilebiliyor.

Demo adresindeki uygulama da kullanıcı onayıyla çalışıyor. Ana sayfasından nelerin ölçüldüğünü ve örnek görünümü inceleyebilirsiniz. Denemek isteyenler benimle iletişime geçebilir.

## Kurulum

Projeyi Laravel ile geliştirdim. Docker üzerinden çalıştırılabiliyor.

Kaynak kodlarını indirip aşağıdaki komutlarla kuruluma başlanabilir:

```bash
git clone https://github.com/bmericc/seo-ai-checker
cd seo-ai-checker

docker network create npm_proxy

cp .env.docker.example .env

docker compose build
docker compose up -d

docker compose exec app php artisan migrate
```

`.env` dosyasında Google OAuth, PageSpeed Insights ve kullanılacak yapay zekâ servislerinin API ayarlarının yapılandırılması gerekiyor.

Varsayılan adres:

`http://localhost:8080`

Gerçek kullanımda uygulamanın önüne HTTPS sonlandıran bir reverse proxy koymak gerekiyor. nginx, Caddy veya Nginx Proxy Manager kullanılabilir.

Docker kullanmadan kurulum ve diğer yapılandırma ayrıntıları GitHub deposundaki belgelerde bulunuyor.

### Kurulumdan sonra dikkat edilmesi gerekenler

Google'a doğrudan gönderilen sorgular bazen CAPTCHA veya olağandışı trafik yanıtıyla sonuçlanabiliyor. Uygulama bunu engellenmiş sorgu olarak raporluyor. Düzenli takip için DataForSEO kullanmak daha uygun olabilir.

PageSpeed Insights API'si anahtarsız da kullanılabiliyor ancak kota kısıtlamaları nedeniyle bir API anahtarı tanımlamak faydalı.

Bir de tarama sıklığına dikkat etmek gerekiyor. Özellikle dış servislere ve arama motorlarına gönderilen isteklerde makul sınırlar kullanmak önemli.

## Testler

Projede 400'e yakın otomatik test bulunuyor.

Google sonuçlarının ayrıştırılması, yapay zekâ API yanıtları ve OAuth akışları gibi bölümleri gerçek servislere bağımlı olmadan, sahte yanıtlarla test ediyorum.

GitHub Actions üzerinden her değişiklikte testler çalıştırılıyor.

Özellikle Google sonuç sayfası gibi sık değişen yapılara bağımlı bir uygulamada testlerin önemli olduğunu düşünüyorum. Küçük bir HTML değişikliği bile sonuçların yanlış ayrıştırılmasına neden olabiliyor.

## Bundan sonra

Uygulama şu anda çalışıyor ve geliştirmeye devam ediyorum.

En çok uğraştıran konulardan biri Google'ın sonuç sayfasındaki değişiklikler. Özellikle AI Overview tespitinde tek bir yönteme güvenmek mümkün değil. Bu bölümün zaman içinde bakım gerektireceği açık.

Bunun dışında yapay zekâ asistanlarındaki görünürlüğü daha uzun dönemli karşılaştırmak, rakip analizlerini geliştirmek ve raporları daha kullanışlı hâle getirmek istiyorum.

Şimdilik kendi ihtiyaçlarımızı karşılayan bir araç ortaya çıktı. Başkalarının da kullanmasıyla farklı ihtiyaçların ve eksiklerin ortaya çıkacağını tahmin ediyorum.

Kaynak kodları GitHub'da:

**https://github.com/bmericc/seo-ai-checker**

Deneyenlerin karşılaştıkları sorunları veya geliştirme önerilerini GitHub üzerinden paylaşmaları işime yarar. Katkıda bulunmak isteyenlerin pull request'lerini de bekliyorum.

---

**Önerilen SEO başlığı:** SEO ve Yapay Zekâ Görünürlüğü Takibi – Açık Kaynak Araç

**Önerilen meta açıklaması:** Google sıralamalarını, AI Overview kaynaklarını ve ChatGPT, Claude, Gemini görünürlüğünü takip etmek için geliştirdiğim açık kaynak SEO aracı.

**Önerilen URL:** `/seo-ai-overview-checker`