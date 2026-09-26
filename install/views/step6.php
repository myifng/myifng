<form method="post" class="panel">
  <input type="hidden" name="_t" value="<?= h(itoken()) ?>">
  <div class="panel-head"><h2>कदम 6: डेमो डेटा</h2></div>
  <div class="panel-body">
    <p>क्या आप डेमो खाते जोड़ना चाहते हैं? इससे हर रोल (एडमिन, संपादक, रिपोर्टर, कर्मचारी) कैसा दिखता है, यह तुरंत देख सकते हैं। आगे के phase में यही विकल्प डेमो ख़बरें, श्रेणियाँ और रिपोर्टर भी जोड़ेगा।</p>
    <div class="form-check form-switch fs-5"><input class="form-check-input" type="checkbox" role="switch" name="demo" value="1" id="demo" checked><label class="form-check-label" for="demo">हाँ, डेमो डेटा जोड़ें</label></div>
    <p class="small text-body-secondary mt-2 mb-0">डेमो खातों का पासवर्ड अगले पेज पर दिखेगा। लाइव साइट पर बाद में इन्हें “यूज़र” से हटा दें।</p>
  </div>
  <div class="panel-foot"><button class="btn btn-brand" type="submit"><i class="fa-solid fa-flag-checkered me-1"></i>इंस्टॉल पूरा करें</button></div>
</form>
