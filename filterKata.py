import json

# --- muat data awal ---
with open("kbbi_words_simple.json", "r", encoding="utf-8") as f:
    words = json.load(f)

# whitelist kata umum & formal lazim
whitelist = {
    "syarat","syukur","syariah","khusus","khawatir","akhir","akhirnya","akhirat",
    "ekonomi","politik","administrasi","pemerintah","universitas","sekolah","kelas",
    "guru","murid","belajar","rapat","laporan","dokumen","kerja","pegawai","karyawan",
    "perusahaan","uang","harga","jual","beli","pasar","toko",
    "rumah","kamar","meja","kursi","pintu","jendela","makan","minum","tidur",
    "ayah","ibu","bapak","anak","adik","kakak","teman","sahabat","istri","suami",
    "jalan","mobil","motor","bus","kereta","ojek","desa","kota","provinsi"
}

filtered = []
for w in words:
    lw = w.lower()

    # selalu simpan kata di whitelist
    if lw in whitelist:
        filtered.append(w)
        continue

    # buang kata yang sangat panjang
    if len(lw) > 12:
        continue

    # buang kata teknis/asing dengan huruf tertentu
    if any(seq in lw for seq in ["x","q","ph","zh"]):
        continue

    # kecualikan kh/sy tapi simpan jika ada di whitelist
    if ("kh" in lw or "sy" in lw) and lw not in whitelist:
        continue

    # hanya huruf atau tanda hubung
    if not all(c.isalpha() or c in "- " for c in lw):
        continue

    # panjang minimal
    if len(lw) < 2:
        continue

    filtered.append(w)

print(f"Awal: {len(words)} kata, Sisa: {len(filtered)} kata")

with open("kbbi_words_lazim_mid.json", "w", encoding="utf-8") as f:
    json.dump(filtered, f, ensure_ascii=False, indent=2)
