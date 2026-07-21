<?php
session_start();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BingYan工作室 - 登录/注册</title>
    <script src="https://static.geetest.com/static/js/gt.0.5.0.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft Yahei", Arial, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }
        .tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            font-weight: 500;
            color: #6c757d;
            transition: all 0.3s ease;
            border: none;
            background: none;
            font-size: 16px;
        }
        .tab.active {
            color: #667eea;
            background: white;
            border-bottom: 3px solid #667eea;
        }
        .tab:hover { background: #e9ecef; }
        .tab.active:hover { background: white; }
        .form-container { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .captcha-box {
            margin-bottom: 15px;
        }
        .captcha-tip {
            font-size: 12px;
            color: #888;
            margin-bottom: 8px;
        }
        .submit-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .submit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .form { display: none; }
        .form.active { display: block; }
        .error {
            color: #dc3545;
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }
        .success {
            color: #28a745;
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo h1 {
            font-size: 28px;
            color: #667eea;
            font-weight: bold;
        }
        .logo p {
            color: #6c757d;
            font-size: 14px;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>BingYan工作室</h1>
            <p>欢迎来到聊天室</p>
        </div>
        <div class="tabs">
            <button class="tab active" onclick="showTab('login')">登录</button>
            <button class="tab" onclick="showTab('register')">注册</button>
        </div>
        <div class="form-container">
            <form id="loginForm" class="form active" onsubmit="return handleLoginSubmit(event)">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" id="loginUsername" name="username" required placeholder="请输入用户名">
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" id="loginPassword" name="password" required placeholder="请输入密码">
                </div>
                <div class="captcha-box">
                    <div class="captcha-tip">请先完成人机验证</div>
                    <div id="loginCaptcha"></div>
                </div>
                <div id="loginError" class="error"></div>
                <button type="submit" class="submit-btn" id="loginBtn" disabled>登录</button>
            </form>
            <form id="registerForm" class="form" onsubmit="return handleRegisterSubmit(event)">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" id="regUsername" name="username" required placeholder="请输入用户名" minlength="3" maxlength="20">
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" id="regPassword" name="password" required placeholder="请输入密码" minlength="6">
                </div>
                <div class="form-group">
                    <label>确认密码</label>
                    <input type="password" id="regConfirmPassword" name="confirm_password" required placeholder="请再次输入密码">
                </div>
                <div class="captcha-box">
                    <div class="captcha-tip">请先完成人机验证</div>
                    <div id="registerCaptcha"></div>
                </div>
                <div id="regError" class="error"></div>
                <button type="submit" class="submit-btn" id="regBtn" disabled>注册</button>
            </form>
        </div>
    </div>

    <script>
        var loginCaptchaObj = null;
        var registerCaptchaObj = null;
        var loginCaptchaResult = null;
        var registerCaptchaResult = null;

        function initCaptcha(target, btn, holder) {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'geetest_register.php?t=' + Date.now(), true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var d = JSON.parse(xhr.responseText);
                    initGeetest({
                        gt: d.gt,
                        challenge: d.challenge,
                        offline: !d.success,
                        new_captcha: d.new_captcha,
                        product: 'popup',
                        width: '100%',
                        api_server: 'api.geevisit.com'
                    }, function(c) {
                        holder.captchaObj = c;
                        c.appendTo('#' + target);
                        c.onReady(function() {}).onSuccess(function() {
                            document.getElementById(btn).disabled = false;
                            holder.result = c.getValidate();
                        }).onError(function() {
                            alert('验证码加载出错，请刷新页面重试');
                        });
                    });
                } else {
                    document.getElementById(target).innerHTML = '<span style="color:red;font-size:13px;">验证码服务不可用</span>';
                }
            };
            xhr.onerror = function() {
                document.getElementById(target).innerHTML = '<span style="color:red;font-size:13px;">验证码服务不可用</span>';
            };
            xhr.send();
        }

        var loginHolder = {}, regHolder = {};
        initCaptcha('loginCaptcha', 'loginBtn', loginHolder);
        initCaptcha('registerCaptcha', 'regBtn', regHolder);

        function showTab(t) {
            document.querySelectorAll('.tab').forEach(function(x) { x.classList.remove('active'); });
            document.querySelectorAll('.form').forEach(function(x) { x.classList.remove('active'); });
            document.querySelectorAll('.tab')[t === 'login' ? 0 : 1].classList.add('active');
            document.getElementById(t + 'Form').classList.add('active');
        }

        function post(url, data, cb) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.onload = function() { cb(xhr.status === 200 ? JSON.parse(xhr.responseText) : { error: '请求失败' }); };
            xhr.send(data);
        }

        function handleLoginSubmit(e) {
            e.preventDefault();
            var el = document.getElementById('loginError');
            el.style.display = 'none';
            if (!loginHolder.result) { el.innerText = '请先完成人机验证'; el.style.display = 'block'; return false; }
            var fd = new FormData(e.target);
            fd.append('geetest_challenge', loginHolder.result.geetest_challenge);
            fd.append('geetest_validate', loginHolder.result.geetest_validate);
            fd.append('geetest_seccode', loginHolder.result.geetest_seccode);
            post('login_process.php', fd, function(d) {
                if (d.success) location.href = d.redirect || 'chat.php';
                else { el.innerText = d.error || '登录失败'; el.style.display = 'block'; loginHolder.result = null; if (loginHolder.captchaObj) loginHolder.captchaObj.reset(); document.getElementById('loginBtn').disabled = true; }
            });
            return false;
        }

        function handleRegisterSubmit(e) {
            e.preventDefault();
            var el = document.getElementById('regError');
            el.style.display = 'none';
            if (document.getElementById('regPassword').value !== document.getElementById('regConfirmPassword').value) {
                el.innerText = '两次密码不一致'; el.style.display = 'block'; return false;
            }
            if (!regHolder.result) { el.innerText = '请先完成人机验证'; el.style.display = 'block'; return false; }
            var fd = new FormData(e.target);
            fd.append('geetest_challenge', regHolder.result.geetest_challenge);
            fd.append('geetest_validate', regHolder.result.geetest_validate);
            fd.append('geetest_seccode', regHolder.result.geetest_seccode);
            post('register.php', fd, function(d) {
                if (d.success) location.href = d.redirect || 'chat.php';
                else { el.innerText = d.error || '注册失败'; el.style.display = 'block'; regHolder.result = null; if (regHolder.captchaObj) regHolder.captchaObj.reset(); document.getElementById('regBtn').disabled = true; }
            });
            return false;
        }
    </script>
</body>
</html>
