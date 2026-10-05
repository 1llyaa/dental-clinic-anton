#!/usr/bin/env bash
# End-to-end smoke test of the admin against ./scripts/local-server.sh.
# Usage: tests/e2e/admin-smoke.sh   (expects http://localhost:8080, password lisov-local-test)
set -uo pipefail
cd "$(dirname "$0")/../.."
B=${BASE:-http://localhost:8080}
PW=${PASSWORD:-lisov-local-test}
J=$(mktemp -d)
pass=0; fail=0
check() { if eval "$2"; then echo "  ✓ $1"; pass=$((pass+1)); else echo "  ✗ $1"; fail=$((fail+1)); fi; }
csrf() { curl -s -b "$J/c" -c "$J/c" "$B/admin/" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
# buffer output: if curl were killed by SIGPIPE (grep -q) it would not save the cookie jar
post() { curl -s -b "$J/c" -c "$J/c" -L "$B/admin/" "$@" > "$J/last"; cat "$J/last"; }
json() { curl -s "$B/data/$1.json" | python3 -c "import json,sys; d=json.load(sys.stdin); print($2)"; }

rm -f .local-server/data/private/login-attempts.json

echo "photo fixture"
docker run --rm -v "$J:/out" zubni-lisov-php php -r '
  $w=4000; $h=3000; $im=imagecreatetruecolor($w,$h);
  for($y=0;$y<$h;$y+=3) for($x=0;$x<$w;$x+=3) imagefilledrectangle($im,$x,$y,$x+2,$y+2,mt_rand(0,0xFFFFFF));
  imagejpeg($im,"/out/photo.jpg",86); imagejpeg($im,"/out/huge.jpg",95);'
echo '<?php echo "PWNED";' > "$J/shell.jpg"
echo "  photo.jpg = $(( $(wc -c < "$J/photo.jpg") / 1024 )) kB"

echo "login"
T=$(csrf)
check "save with expired session explains logout" "post -d csrf=$T -d action=save_hours | grep -q 'byli odhlášeni'"
T=$(csrf)
check "wrong password rejected" "post -d csrf=$T -d action=login -d password=nope | grep -q 'Nesprávné heslo'"
T=$(csrf)
check "correct password opens dashboard" "post -d csrf=$T -d action=login --data-urlencode password=$PW | grep -q 'Odhlásit'"
check "POST without CSRF token is refused" "post -d action=save_hours | grep -q 'Platnost stránky vypršela'"

echo "banner"
T=$(csrf)
check "over-limit photo ($(( $(wc -c < "$J/huge.jpg") / 1024 )) kB) gets clear message" "post -F csrf=$T -F action=save_banner -F active=1 -F text=x -F 'image=@$J/huge.jpg;type=image/jpeg' | grep -q 'příliš velký'"
T=$(csrf)
post -F csrf=$T -F action=save_banner -F active=1 -F "title=Dovolená" -F "text=Zavřeno od 14. 7. <b>do</b> 25. 7." \
  -F from= -F until=2099-12-31 -F "image=@$J/photo.jpg;type=image/jpeg" > "$J/out.html"
check "save shows confirmation" "grep -q 'Uloženo. Na webu se změna projeví do minuty.' $J/out.html"
check "banner.json active with title" "[ \"\$(json banner 'd[\"active\"], d[\"title\"]')\" = 'True Dovolená' ]"
check "text stored raw, escaped in admin" "json banner 'd[\"text\"]' | grep -q '<b>do</b>' && grep -q '&lt;b&gt;do&lt;/b&gt;' $J/out.html"
IMG=$(json banner 'd["image"]')
check "image path generated, not original name" "[[ \"$IMG\" =~ ^/uploads/banner-[0-9]+-[a-f0-9]{6}\.jpg$ ]]"
curl -s -o "$J/served.jpg" -w '%{http_code} %{content_type}' "$B$IMG" > "$J/meta"
check "image served as JPEG" "grep -q '200 image/jpeg' $J/meta"
KB=$(( $(wc -c < "$J/served.jpg") / 1024 ))
check "image re-encoded small (${KB} kB)" "[ $KB -lt 700 ]"
check "backup created" "ls .local-server/data/backups/banner-*.json >/dev/null 2>&1"

T=$(csrf)
check "until before from rejected with message" "post -F csrf=$T -F action=save_banner -F active=1 -F text=x -F from=2026-08-10 -F until=2026-08-01 | grep -q 'dříve než datum'"
T=$(csrf)
check "chosen photo on a rejected save is called out" "post -F csrf=$T -F action=save_banner -F active=1 -F text=x -F from=2026-08-10 -F until=2026-08-01 -F 'image=@$J/photo.jpg;type=image/jpeg' | grep -q 'Vybraný obrázek se neuložil'"
check "rejected save keeps previous file" "[ \"\$(json banner 'd[\"image\"]')\" = '$IMG' ]"
T=$(csrf)
check "PHP disguised as JPG rejected" "post -F csrf=$T -F action=save_banner -F active=1 -F text=x -F 'image=@$J/shell.jpg;type=image/jpeg' | grep -q 'není fotka'"
check "no PHP in uploads" "! ls .local-server/uploads/ | grep -q 'shell'"

T=$(csrf)
post -F csrf=$T -F action=save_banner -F active=1 -F "title=Dovolená" -F text=x -F remove_image=1 > /dev/null
check "remove image clears field" "[ \"\$(json banner 'd[\"image\"]')\" = 'None' ]"
check "old image file deleted" "[ ! -f .local-server/uploads/$(basename "$IMG") ]"

T=$(csrf)
post -F csrf=$T -F action=save_banner -F active=1 -F "title=Dovolená" -F text=x -F until=2020-01-01 > "$J/out.html"
check "past until shows warning" "grep -q 'datum do je v minulosti' $J/out.html"

echo "hours"
T=$(csrf)
post -d csrf=$T -d action=save_hours --data-urlencode 'value[0]=8:00 – 18:00' -d 'value[1]=Zavřeno' -d 'value[2]=x' -d 'value[3]=x' -d 'value[4]=x' -d 'value[5]=Zavřeno' -d 'value[6]=Zavřeno' --data-urlencode 'note=Polední pauza 12:00 – 12:30' > /dev/null
check "hours saved" "[ \"\$(json hours 'd[\"regular\"][0][\"value\"]')\" = '8:00 – 18:00' ]"

echo "news"
T=$(csrf)
post -d csrf=$T -d action=save_news -d id= -d date=2099-01-01 --data-urlencode 'title=Test novinka' -d text=abc > /dev/null
ID=$(json news '[i["id"] for i in d["items"] if i["title"]=="Test novinka"][0]')
check "news added on top" "[ \"\$(json news 'd[\"items\"][0][\"id\"]')\" = '$ID' ]"
T=$(csrf)
post -d csrf=$T -d action=save_news -d id=$ID -d date=2099-01-01 --data-urlencode 'title=Upravená novinka' -d text=abc > /dev/null
check "news edited" "[ \"\$(json news 'd[\"items\"][0][\"title\"]')\" = 'Upravená novinka' ]"
T=$(csrf)
post -d csrf=$T -d action=delete_news -d id=$ID > /dev/null
check "news deleted" "! curl -s $B/data/news.json | grep -q '$ID'"

echo "logout + lockout"
T=$(csrf)
check "logout returns to login" "post -d csrf=$T -d action=logout | grep -q 'Přihlásit se'"
for i in 1 2 3 4 5; do T=$(csrf); post -d csrf=$T -d action=login -d password=bad$i > "$J/out.html"; done
check "5 wrong passwords lock login" "grep -q 'Příliš mnoho nesprávných pokusů' $J/out.html"
T=$(csrf)
check "correct password refused while locked" "! post -d csrf=$T -d action=login --data-urlencode password=$PW | grep -q 'Odhlásit'"
rm -f .local-server/data/private/login-attempts.json

# restore a neutral banner for manual browsing
T=$(csrf); post -d csrf=$T -d action=login --data-urlencode password=$PW >/dev/null
T=$(csrf); post -F csrf=$T -F action=save_banner -F text= >/dev/null

rm -rf "$J"
echo; echo "$pass passed, $fail failed"
[ $fail -eq 0 ]
