<div class="consent-wrapper">
  <h2>الموافقة على الشروط</h2>
  <p>للاستمرار باستخدام منصة "كيف التعليمية"، يجب الموافقة على:</p>
    <ul>
        <li>
            <a href="<?= site_url('auth/privacy') ?>" target="_blank">
                <i class="fas fa-shield-alt"></i>
                سياسة الخصوصية
            </a>
        </li>
        <li>
            <a href="<?= site_url('auth/terms') ?>" target="_blank">
                <i class="fas fa-file-contract"></i>
               الشروط والأحكام
            </a>
        </li>
    </ul>
  <form method="post" action="<?= site_url('auth/accept_terms') ?>">
    <button name="action" value="accept" class="accept-btn">
        <i class="fas fa-check-circle"></i>
        أوافق وأتابع
    </button>
    <button name="action" value="decline" class="decline-btn" onclick="return confirm('هل أنت متأكد أنك لا تريد استخدام المنصة؟')">
        <i class="fas fa-times-circle"></i>
        لا أوافق، احذف حسابي
    </button>
  </form>
</div>
<style>
     .consent-wrapper {
            background-color: var(--background);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 2.5rem;
            max-width: 700px;
            width: 100%;
            text-align: center;
            animation: slideIn 0.8s ease-out;
            border: 1px solid rgba(142, 202, 230, 0.3);
        }

        .consent-wrapper h2 {
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
            color: var(--primary-darker);
            position: relative;
            padding-bottom: 1rem;
        }

        .consent-wrapper h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            left: 0;
            width: 80px;
            height: 4px;
            background: linear-gradient(to right, var(--accent), var(--accent-dark));
            border-radius: 10px;
            margin: 0 auto;
        }

        .consent-wrapper p {
            font-size: 1.2rem;
            color: var(--text-light);
            margin-bottom: 2rem;
            line-height: 1.7;
        }

        .consent-wrapper ul {
            list-style: none;
            margin: 2rem 0;
            padding: 1.5rem;
            background-color: rgba(142, 202, 230, 0.1);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary);
        }

        .consent-wrapper li {
            margin-bottom: 1.2rem;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .consent-wrapper a {
            color: var(--primary-dark);
            text-decoration: none;
            font-weight: bold;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .consent-wrapper a:hover {
            color: var(--primary-darker);
            transform: translateX(-5px);
        }

        .consent-wrapper a i {
            font-size: 0.9rem;
        }

        .consent-wrapper form {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            justify-content: center;
            margin-top: 1.5rem;
        }

        .consent-wrapper button {
            padding: 1rem 2.5rem;
            border-radius: var(--border-radius);
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            z-index: 1;
            flex: 1;
            min-width: 220px;
            justify-content: center;
        }

        .consent-wrapper button::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 0;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: width 0.5s ease;
            z-index: -1;
        }

        .consent-wrapper button:hover::before {
            width: 100%;
        }

        .accept-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--background);
        }

        .accept-btn:hover {
            box-shadow: 0 7px 20px rgba(33, 158, 188, 0.4);
        }

        .decline-btn {
            background: linear-gradient(135deg, var(--rejected), var(--rejected-dark));
            color: var(--background);
        }

        .decline-btn:hover {
            box-shadow: 0 7px 20px rgba(231, 76, 60, 0.3);
        }
</style>