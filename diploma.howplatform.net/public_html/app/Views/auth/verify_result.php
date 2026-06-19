<main class="followup-main" style="font-family:'Almarai',sans-serif;">

  <?php if ($status === 'success'): ?>
    <div class="followup-container success-state">
      <i class="fas fa-check-circle status-icon success-icon"></i>
      <h2>تم تفعيل حسابك بنجاح!</h2>

      <div class="status-card">
        <p>يمكنك الآن البدء باستخدام المنصة. اضغط أدناه للمتابعة.</p>
      </div>

      <div class="action-buttons">
        <a href="<?= site_url('auth/login') ?>" class="btn btn-primary">
          <i class="fas fa-sign-in-alt"></i>
          تسجيل الدخول
        </a>
        <a href="<?= site_url('/') ?>" class="btn btn-secondary">
          <i class="fas fa-home"></i>
          الصفحة الرئيسية
        </a>
      </div>
    </div>

  <?php elseif ($status === 'already'): ?>
    <div class="followup-container info-state">
      <i class="fas fa-info-circle status-icon info-icon"></i>
      <h2>الحساب مُفعَّل بالفعل</h2>

      <div class="status-card">
        <p>لا حاجة لخطوات إضافية. يمكنك التوجّه مباشرة لتسجيل الدخول.</p>
      </div>

      <div class="action-buttons">
        <a href="<?= site_url('auth/login') ?>" class="btn btn-primary">
          <i class="fas fa-sign-in-alt"></i>
          تسجيل الدخول
        </a>
        <a href="<?= site_url('/') ?>" class="btn btn-secondary">
          <i class="fas fa-home"></i>
          الصفحة الرئيسية
        </a>
      </div>
    </div>

  <?php elseif ($status === 'invalid'): ?>
    <div class="followup-container error-state">
      <i class="fas fa-exclamation-circle status-icon error-icon"></i>
      <h2>الرابط غير صالح أو منتهي</h2>

      <div class="status-card">
        <p>يبدو أن رابط التفعيل غير صالح أو انتهت صلاحيته. يُرجى طلب رابط جديد أو التواصل مع الدعم.</p>
      </div>

      <div class="action-buttons">
          <a href="<?= site_url('/') ?>" class="btn btn-primary">
          <i class="fas fa-home"></i>
          الصفحة الرئيسية
        </a>
        <a href="mailto:support@howplatform.net" class="btn btn-secondary">
          <i class="fas fa-headset"></i>
          التواصل مع الدعم
        </a>
        
      </div>
    </div>

  <?php else: /* missing */ ?>
    <div class="followup-container error-state">
      <i class="fas fa-exclamation-circle status-icon error-icon"></i>
      <h2>رمز التفعيل غير موجود</h2>

      <div class="status-card">
        <p>تأكد من صحة الرابط الذي فتحته. إذا استمرت المشكلة، تواصل مع فريق الدعم.</p>
      </div>

      <div class="action-buttons">
        <a href="mailto:support@howplatform.net" class="btn btn-secondary">
          <i class="fas fa-headset"></i>
          الدعم الفني
        </a>
        <a href="<?= site_url('auth/login') ?>" class="btn btn-primary">
          <i class="fas fa-sign-in-alt"></i>
          صفحة الدخول
        </a>
      </div>
    </div>
  <?php endif; ?>

</main>

<style>
  /* ====== نفس الستايل المعتمد في صفحة المتابعة ====== */

  .followup-main{
    flex:1;display:flex;align-items:center;justify-content:center;
    padding:20px;animation:fadeIn 1s ease;
  }

  .followup-container{
    max-width:700px;width:100%;background:var(--background);
    border-radius:var(--border-radius);box-shadow:var(--shadow);
    padding:3rem;text-align:center;border:1px solid rgba(142,202,230,.3);
    position:relative;overflow:hidden;
  }
  .followup-container::before{
    content:'';position:absolute;top:0;right:0;width:100%;height:5px;
    background:linear-gradient(to right,var(--accent),var(--accent-dark));
    border-radius:var(--border-radius) var(--border-radius) 0 0;
  }

  .status-icon{font-size:5rem;margin-bottom:.5rem;animation:pulse 2s infinite;}
  .success-icon{color:var(--approved);}
  .error-icon{color:var(--rejected);}
  .info-icon{color:var(--primary);}

  .followup-container h2{
    font-size:2.2rem;margin-bottom:1.5rem;color:var(--primary-darker);
  }
  .success-state h2{color:var(--approved-dark);}
  .error-state h2{color:var(--rejected-dark);}

  .followup-container p{
    font-size:1.25rem;color:var(--text-light);margin-bottom:1.5rem;
    line-height:1.7;max-width:90%;margin-inline:auto;
  }

  .followup-container a{
    color:var(--primary-dark);text-decoration:none;font-weight:bold;
    display:inline-flex;align-items:center;gap:8px;
  }
  .followup-container a:hover{color:var(--primary-darker);}

  .action-buttons{
    display:flex;justify-content:center;gap:1.5rem;margin-top:2rem;flex-wrap:wrap;
  }
  .action-buttons a{color:var(--background);} /* نص الأزرار أبيض */

  .btn{
    padding:1rem 2.5rem;border-radius:var(--border-radius);font-size:1.1rem;font-weight:700;
    cursor:pointer;border:none;display:flex;align-items:center;gap:10px;
    box-shadow:0 4px 15px rgba(0,0,0,.1);position:relative;overflow:hidden;z-index:1;text-decoration:none;
  }
  .btn::before{
    content:'';position:absolute;top:0;right:0;width:0;height:100%;
    background:rgba(255,255,255,.2);z-index:-1;transition:width .25s ease;
  }
  .btn:hover::before{width:100%;}

  .btn-primary{
    background:linear-gradient(135deg,var(--primary),var(--primary-dark));
    color:var(--background);
  }
  .btn-primary:hover{box-shadow:0 7px 20px rgba(33,158,188,.4);}

  .btn-secondary{
    background:linear-gradient(135deg,var(--accent-light),var(--accent));
    color:var(--primary-darker);
  }
  .btn-secondary:hover{box-shadow:0 7px 20px rgba(255,183,3,.4);}

  .status-card{
    background:var(--approved-light);border-radius:var(--border-radius);
    padding:1.5rem;margin:2rem 0;border-left:5px solid var(--approved);text-align:right;
  }
  .error-state .status-card{
    background:var(--rejected-light);border-left-color:var(--rejected);
  }
  .info-state .status-card{
    background:var(--primary-light);border-left-color:var(--primary);
  }

  /* حركات بسيطة (في حال لم تكن معرفة سابقًا) */
  @keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
  @keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.04)}}
</style>
