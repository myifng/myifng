<?php $this->layout('layouts/front'); ?>
<div class="wrap narrow">
  <div class="box done-box">
    <i class="fa-solid fa-circle-check"></i>
    <h1 class="list-title">आवेदन जमा हो गया</h1>
    <p>आपकी आवेदन संख्या:</p>
    <p class="app-no"><?= e($appNo) ?></p>
    <p class="small-note">इसे लिख लें। यही संख्या और आपका मोबाइल नंबर डालकर आप कभी भी आवेदन की स्थिति देख सकते हैं। यह जानकारी आपके ईमेल पर भी भेजी गई है।</p>
    <a class="btn" href="<?= e(route('application.status')) ?>">आवेदन की स्थिति देखें</a>
  </div>
</div>
