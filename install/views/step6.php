<form method="post" class="panel" id="demoForm">
  <input type="hidden" name="_t" value="<?= h(itoken()) ?>">
  <div class="panel-head"><h2>कदम 6: डेमो डेटा</h2></div>
  <div class="panel-body">
    <p>डेमो डेटा जोड़ने पर वेबसाइट तुरंत भरी-पूरी दिखेगी, ताकि आप डिज़ाइन और हर मॉड्यूल को आज़मा सकें:</p>
    <ul class="small row row-cols-1 row-cols-sm-2 g-1 ps-3 mb-3">
      <li>31 नमूना ख़बरें (तस्वीर, टैग, ज़िला सहित)</li>
      <li>लाइव ब्लॉग, 3 ब्रेकिंग न्यूज़</li>
      <li>वीडियो, फ़ोटो गैलरी, वेब स्टोरी</li>
      <li>आज का ई-पेपर, लाइव टीवी</li>
      <li>पोल, फ़ैक्ट चेक, 2 नौकरियाँ</li>
      <li>4 विज्ञापन, 2 रिपोर्टर (ID कार्ड सहित)</li>
      <li>डेमो खाते: एडमिन, संपादक, रिपोर्टर, कर्मचारी</li>
    </ul>
    <div class="form-check form-switch fs-5"><input class="form-check-input" type="checkbox" role="switch" name="demo" value="1" id="demo" checked><label class="form-check-label" for="demo">हाँ, डेमो डेटा जोड़ें</label></div>
    <p class="small text-body-secondary mt-2 mb-0">लाइव होने से पहले <b>एडमिन → सिस्टम → “डेमो डेटा हटाएँ”</b> से सब एक क्लिक में हट जाएगा (आपकी अपनी सामग्री पर असर नहीं)। डेमो खातों का पासवर्ड अगले पेज पर दिखेगा।</p>
  </div>
  <div class="panel-foot"><button class="btn btn-brand" type="submit" id="finishBtn"><i class="fa-solid fa-flag-checkered me-1"></i>इंस्टॉल पूरा करें</button></div>
</form>
<script>
document.getElementById('demoForm').addEventListener('submit', function () {
  var b = document.getElementById('finishBtn');
  setTimeout(function () { b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> इंस्टॉल हो रहा है… (कुछ सेकंड)'; }, 0);
});
</script>
