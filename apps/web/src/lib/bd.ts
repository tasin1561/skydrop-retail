// Bangladesh divisions and districts for the address form.
export const BD_DIVISIONS: Record<string, string[]> = {
  Dhaka: ["Dhaka", "Gazipur", "Narayanganj", "Narsingdi", "Manikganj", "Munshiganj", "Tangail", "Faridpur", "Gopalganj", "Kishoreganj", "Madaripur", "Rajbari", "Shariatpur"],
  Chattogram: ["Chattogram", "Cox's Bazar", "Cumilla", "Feni", "Noakhali", "Lakshmipur", "Chandpur", "Brahmanbaria", "Rangamati", "Khagrachhari", "Bandarban"],
  Rajshahi: ["Rajshahi", "Bogura", "Pabna", "Sirajganj", "Natore", "Naogaon", "Chapainawabganj", "Joypurhat"],
  Khulna: ["Khulna", "Jashore", "Satkhira", "Bagerhat", "Kushtia", "Jhenaidah", "Magura", "Narail", "Chuadanga", "Meherpur"],
  Barishal: ["Barishal", "Patuakhali", "Bhola", "Pirojpur", "Jhalokati", "Barguna"],
  Sylhet: ["Sylhet", "Moulvibazar", "Habiganj", "Sunamganj"],
  Rangpur: ["Rangpur", "Dinajpur", "Kurigram", "Gaibandha", "Nilphamari", "Lalmonirhat", "Thakurgaon", "Panchagarh"],
  Mymensingh: ["Mymensingh", "Jamalpur", "Netrokona", "Sherpur"],
};

/** Bangla names for divisions and districts (shown in Bangla mode; the stored value stays English). */
export const BD_NAMES_BN: Record<string, string> = {
  Dhaka: "ঢাকা", Gazipur: "গাজীপুর", Narayanganj: "নারায়ণগঞ্জ", Narsingdi: "নরসিংদী", Manikganj: "মানিকগঞ্জ", Munshiganj: "মুন্সীগঞ্জ",
  Tangail: "টাঙ্গাইল", Faridpur: "ফরিদপুর", Gopalganj: "গোপালগঞ্জ", Kishoreganj: "কিশোরগঞ্জ", Madaripur: "মাদারীপুর", Rajbari: "রাজবাড়ী",
  Shariatpur: "শরীয়তপুর", Chattogram: "চট্টগ্রাম", "Cox's Bazar": "কক্সবাজার", Cumilla: "কুমিল্লা", Feni: "ফেনী", Noakhali: "নোয়াখালী",
  Lakshmipur: "লক্ষ্মীপুর", Chandpur: "চাঁদপুর", Brahmanbaria: "ব্রাহ্মণবাড়িয়া", Rangamati: "রাঙ্গামাটি", Khagrachhari: "খাগড়াছড়ি",
  Bandarban: "বান্দরবান", Rajshahi: "রাজশাহী", Bogura: "বগুড়া", Pabna: "পাবনা", Sirajganj: "সিরাজগঞ্জ", Natore: "নাটোর", Naogaon: "নওগাঁ",
  Chapainawabganj: "চাঁপাইনবাবগঞ্জ", Joypurhat: "জয়পুরহাট", Khulna: "খুলনা", Jashore: "যশোর", Satkhira: "সাতক্ষীরা", Bagerhat: "বাগেরহাট",
  Kushtia: "কুষ্টিয়া", Jhenaidah: "ঝিনাইদহ", Magura: "মাগুরা", Narail: "নড়াইল", Chuadanga: "চুয়াডাঙ্গা", Meherpur: "মেহেরপুর",
  Barishal: "বরিশাল", Patuakhali: "পটুয়াখালী", Bhola: "ভোলা", Pirojpur: "পিরোজপুর", Jhalokati: "ঝালকাঠি", Barguna: "বরগুনা",
  Sylhet: "সিলেট", Moulvibazar: "মৌলভীবাজার", Habiganj: "হবিগঞ্জ", Sunamganj: "সুনামগঞ্জ", Rangpur: "রংপুর", Dinajpur: "দিনাজপুর",
  Kurigram: "কুড়িগ্রাম", Gaibandha: "গাইবান্ধা", Nilphamari: "নীলফামারী", Lalmonirhat: "লালমনিরহাট", Thakurgaon: "ঠাকুরগাঁও",
  Panchagarh: "পঞ্চগড়", Mymensingh: "ময়মনসিংহ", Jamalpur: "জামালপুর", Netrokona: "নেত্রকোনা", Sherpur: "শেরপুর",
};

/** A division or district name in the shopper's language (English values are what we store and send). */
export function bdName(name: string, lang: "en" | "bn"): string {
  return lang === "bn" ? (BD_NAMES_BN[name] ?? name) : name;
}
