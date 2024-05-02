$(document).ready(function () {
    class MainManager {
        constructor() {
            this.initialize();
            this.typeCopy = null;
            this.codeSVG = null;
        }

        startLoader() {
            $('#preloader').removeAttr('hidden')
        }

        endLoader() {
            $('#preloader').attr('hidden', true)
        }

        validateCode() {
            $('.settings-promocode-input').on('input', function () {
                let value = $(this).val();
                let onlyAlphanumeric = value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
                if (onlyAlphanumeric.length > 20) {
                    onlyAlphanumeric = onlyAlphanumeric.slice(0, 20);
                }
                $(this).val(onlyAlphanumeric);
            })
        }

        checkingAuthorize() {
            ManagerMassage.postMessage({ type: 'get_user' }).then(response => {
                const user = response.data.user;
                if (user?.login && user?.token) {
                    this.startLoader();
                    ManagerRequest.sendPost('checkingAuthorize', { token: user.token });
                }
            });
        }

        startUse(user) {
            $('#login, #confirmEmail').attr('hidden', true)
            $('#work').removeAttr('hidden')
            $('.header-user-login').text(user['login'])
            $('.header-user-img').text(user['login'].substr(0, 1).toUpperCase())
            if (user.subscribe != null) {
                $('#work .body').removeClass('non-premium')
                $('#work .header-subscribtion').removeAttr('hidden').text('Subscription until ' + user.subscribe)
            } else {
                $('#work .body').addClass('non-premium')
                $('#work .header-button').removeAttr('hidden')
            }
        }

        endUse() {
            this.deleteUser()
            $('#login').removeAttr('hidden')
            $('#work').attr('hidden', true)
            $('#confirmEmail').attr('hidden', true)
            $('.settings').attr('hidden', true)
            $('.header-user-login').text('')
            $('.header-user-img').text('')
        }

        saveUser(user) {
            ManagerMassage.postMessage({ type: 'save_user', user: user });
        }

        deleteUser() {
            ManagerMassage.postMessage({ type: 'save_user', user: null });
        }

        succesActivateCode() {
            ManagerMassage.postMessage({ type: 'get_user' }).then(response => {
                console.log(response);
                if (response.data.user !== null) {
                    $('#work .body').removeClass('non-premium')
                    $('#work .header-button').attr('hidden', true)
                    $('#work .header-subscribtion').removeAttr('hidden').text('Subscription until ' + response.data.user.subscribe)
                    ManagerMassage.postMessage({ type: 'message_success', data: 'Subscription until ' + response.data.user.subscribe })
                }
            });
        }

        startConfirmTimer() {
            let time = 59;
            let startTimer = setInterval(() => {
                if (time <= 0) {
                    clearInterval(startTimer);
                    $('#confirmTimer').html('<span data-send="resendcode">Resend Code</span>');
                } else {
                    time--;
                    let displayTime = time < 10 ? '0' + time : time;
                    $('#confirmTimer').text('New code available in ' + displayTime + ' seconds');
                }
            }, 1000);
        }

        resetForm() {
            $('.settings-promocode-input, .code-input input, .login-input').val('')
        }

        copySVG() {
            ManagerMassage.postMessage({ type: 'copy', typeCopy: this.typeCopy }).then(response => {
                this.copyToClipboard(response.data)
            });
        }

        copyToClipboard(codeSVG) {
            let textArea = document.createElement("textarea");
            textArea.value = codeSVG;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            textArea.style.top = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            document.execCommand('copy');
            textArea.remove();
            $('.success').text('Copied').removeAttr('hidden')
            setTimeout(() => {
                $('.success').attr('hidden', true)
            }, 500)
        }

        checkingSubscribe() {
            ManagerMassage.postMessage({ type: 'get_user' }).then(response => {
                if (response.data.user !== null) {
                    if (response.data.user.subscribe) {
                        this.copySVG()
                    } else {
                        let msg = `Your subscription has lapsed. Please, rejuvenate it by hitting 'Subscribe' within the plugin interface`
                        ManagerRequest.callError(msg)
                    }
                } else {
                    let msg = `Authorization is required to access this feature. Please sign in or create an account.`;
                    ManagerRequest.callError(msg)
                }
            });
        }

        pasteConfirmCode() {
            let textArea = document.createElement("textarea");
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            textArea.style.top = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            document.execCommand('paste');
        
            let value = textArea.value.replace(/\D/g, '');
        
            if (value.length > 6) {
                value = value.substring(0, 6);
            }
        
            const chars = value.split('');
            $('.code-input input').each(function (index, input) {
                if (index < chars.length) {
                    $(input).val(chars[index]);
                }
            });
            textArea.remove();
        }

        validateConfirmCode() {
            const digitCount = $('.code-input input').toArray().reduce((count, input) => {
                return count + $(input).val().length;
            }, 0);

            if (digitCount === 6) {
                $('[data-send="confirm"]').click()
            }
        }

        renderPlans(plans) {
            let result = '';
            plans.forEach(item => {
                result += `
                    <div class="settings-item" data-id="${item.id}">
                        <div class="settings-title">${item.name}</div>
                        <div class="settings-descr">
                            <div class="settings-descr-item">
                                <div class="settings-descr-img"></div>
                                <div class="settings-descr-text">Convert to HTML code</div>
                            </div>
                            <div class="settings-descr-item">
                                <div class="settings-descr-img"></div>
                                <div class="settings-descr-text">Convert to CSS code</div>
                            </div>
                            <div class="settings-descr-item">
                                <div class="settings-descr-img"></div>
                                <div class="settings-descr-text">Convert to IMG code</div>
                            </div>
                        </div>
                        <div class="settings-price">
                            <div class="settings-price-old">$${item.old_price}</div>
                            <div class="settings-price-new">$${item.price}</div>
                        </div>
                        <div class="settings-buy" data-send="subscribe">Buy</div>
                    </div>
                `
            })
            $('.settings-list').append(result)
        }

        renderPlanForRu(data){
            $('.settings-list').html('')
            if(data.country == 'RU'){
                $('.settings-list').append(`
                    <div class="settings-item" data-id="5">
                        <div class="settings-title">1 Month Subscription</div>
                        <div class="settings-descr">
                            <div class="settings-descr-item">
                                <div class="settings-descr-img"></div>
                                <div class="settings-descr-text">Convert to HTML code</div>
                            </div>
                            <div class="settings-descr-item">
                                <div class="settings-descr-img"></div>
                                <div class="settings-descr-text">Convert to CSS code</div>
                            </div>
                            <div class="settings-descr-item">
                                <div class="settings-descr-img"></div>
                                <div class="settings-descr-text">Convert to IMG code</div>
                            </div>
                        </div>
                        <div class="settings-price">
                            <div class="settings-price-old">288RUB</div>
                            <div class="settings-price-new">100RUB</div>
                        </div>
                        <div class="settings-buy" data-send="subscribe">Yookassa</div>
                    </div>
                `)
            }
        }

        initialize() {
            this.validateConfirmCode()
            this.validateCode()
            this.checkingAuthorize()

            $('.body-list-item').click((event) => {
                this.typeCopy = $(event.currentTarget).attr('id')
                this.checkingSubscribe()
            })

            $('.code-input input').on('input', (event) => {
                const currentInput = $(event.currentTarget);
            
                currentInput.val(currentInput.val().replace(/\D/g, ''));
            
                const nextInput = currentInput.next('input');
                this.validateConfirmCode()
            
                if (currentInput.val().length > 0) {
                    nextInput.focus();
                    nextInput.select();
                }
            });
            

            $('.code-input input').on('keydown', (event) => {
                if (event.ctrlKey && event.keyCode === 86) {
                    this.pasteConfirmCode();
                    this.validateConfirmCode()
                } else if (event.keyCode === 8) {
                    const currentInput = $(event.currentTarget);
                    const previousInput = currentInput.prev('input');

                    currentInput.val('');
                    if (previousInput.length > 0) {
                        previousInput.focus();
                        previousInput.select();
                    }
                }
            });
        }
    }

    class PopupManager {
        constructor() {
            this.initialize();
        }

        openConfrimCode() {
            $('#login').attr('hidden', true)
            $('#confirmEmail').removeAttr('hidden')
            $('.code-input input:first-child').focus()
        }

        initialize() {
            $('.header-user, .header-button').click(function () {
                $('.body').attr('hidden', true)
                $('.settings').removeAttr('hidden')
                $('#main').addClass('open')
                ManagerMassage.postMessage({ type: 'settings', flag: true });
            })

            $('.settings-back').click(function () {
                $('.body').removeAttr('hidden')
                $('.settings').attr('hidden', true)
                $('#main').removeClass('open')
                ManagerMassage.postMessage({ type: 'settings', flag: false });
            })
        }
    }

    class MessageManager {
        constructor() {
            this.initialize();
            this.promiseResolver = {};
        }

        initialize() {
            window.addEventListener("message", this.onMessage.bind(this), false);
        }

        postMessage(pluginMessage) {
            parent.postMessage({ pluginMessage }, '*');

            return new Promise(resolve => {
                this.promiseResolver = resolve;
            });
        }

        onMessage(event) {
            if (!event.data.pluginMessage || !this.promiseResolver) return;
            this.promiseResolver(event.data.pluginMessage);
            this.promiseResolver = null;
        }
    }

    class RequestManager {
        constructor() {
            this.url = 'https://svgconverter.ru/api/'
            this.initialize();
        }

        callError(error) {
            ManagerMassage.postMessage({ type: 'message_error', data: error })
        }

        validateEmail(email) {
            var re = /^[\w-]+(\.[\w-]+)*@([\w-]+\.)+[a-zA-Z]{2,7}$/;
            return re.test(email);
        }

        dataProccessing(target) {
            let requestType = $(target).attr('data-send');
            let data = {};

            switch (requestType) {
                case 'signup':
                    let email = $('#login input[type="email"]').val();

                    if (!this.validateEmail(email)) {
                        return this.callError('Please enter a valid email.');
                    }

                    data = {
                        email: email,
                        figma_id: null
                    };

                    ManagerMassage.postMessage({ type: 'figma_id' }).then(response => {
                        data.figma_id = response.data;
                        $('.confirmRepeatEmail').text(email)
                        this.sendPost(requestType, data);
                    });
                    break;
                case 'promocode':
                    let promocode = $('.settings-promocode-input').val()

                    if (promocode.length === 0) {
                        return this.callError('An error occurred while activating the promocode.');
                    }

                    ManagerMassage.postMessage({ type: 'get_user' }).then(response => {
                        if (response.data.user !== null && response.data.user.token !== null) {
                            data = {
                                token: response.data.user.token,
                                promocode: promocode
                            }

                            ManagerRequest.sendPost('activatePromocode', data)
                        }
                    });
                    break;
                case 'confirm':
                    ManagerMassage.postMessage({ type: 'get_user' }).then(response => {
                        const inputs = $('.code-input input').toArray();
                        const values = inputs.map(input => $(input).val()).join('');
                        data = {
                            activationCode: values,
                            figma_id: response.data.figma_id,
                        };
                        ManagerRequest.sendPost('confirmEmail', data)
                    });
                    break;
                case 'exit':
                    ManagerMain.endUse();
                    break;
                case 'resendcode':
                    ManagerMassage.postMessage({ type: 'get_user' }).then(response => {
                        ManagerRequest.sendPost('resendCode', response.data)
                    });
                    ManagerMain.startConfirmTimer()
                    break;
                case 'subscribe':
                    let plan_id = $(target).closest('.settings-item').attr('data-id')
                    ManagerMassage.postMessage({ type: 'get_user' }).then(response => {
                        if (response.data.user !== null) {
                            data = {
                                plan_id: plan_id,
                                token: response.data.user.token
                            }
                            ManagerRequest.sendPost('subscribe', data)
                        }
                    });
                    break;
            }
        }

        initialize() {
            $(document).on('keypress', 'input', (event) => {
                if (event.keyCode == 13) {
                    let button = $(event.currentTarget).closest('.login, .confirm').find('[data-send]');

                    if (button.length > 0) {
                        this.dataProccessing(button)
                    }
                }
            });

            $(document).on('click', '[data-send]', (event) => {
                this.dataProccessing(event.currentTarget)
            })
        }

        sendPost(requestType, data) {
            this.send(requestType, data, 'POST')
        }

        send(requestType, data, method) {
            ManagerMain.startLoader()
            $.ajax({
                url: this.url,
                type: method,
                dataType: "json",
                contentType: "application/json",
                data: JSON.stringify({
                    requestType: requestType,
                    ...data
                }),
                success: (response, status, xhr) => {
                    ManagerMain.endLoader()
                    switch (xhr.status) {
                        case 201:
                            ManagerMain.startConfirmTimer()
                            ManagerPopup.openConfrimCode();
                            break;
                        case 200:
                            switch (response.type_operation) {
                                case 'authorized':
                                case 'confirmed':
                                    ManagerMain.saveUser(response.data)
                                    ManagerMain.startUse(response.data)
                                    ManagerMain.renderPlanForRu(response.data)
                                    ManagerMain.renderPlans(response.plans)
                                    break;
                                case 'activated':
                                    ManagerMain.saveUser(response.data)
                                    ManagerMain.startUse(response.data)
                                    ManagerMain.succesActivateCode()
                                    ManagerMain.resetForm();
                                    break;
                                case 'subscribe':
                                    window.open(response.data.url)
                                    break;
                                default:
                                    break;
                            }
                            break;
                        default:
                            break;
                    }

                },
                error: (response) => {
                    ManagerMain.endLoader()
                    let error = response.responseJSON.error.message
                    switch (response.responseJSON.error.code) {
                        case 1:
                        case 2:
                            ManagerMain.endUse()
                            break;
                    
                        default:
                            break;
                    }
                    this.callError(error);
                }
            });
        }

    }

    const ManagerPopup = new PopupManager();
    const ManagerRequest = new RequestManager();
    const ManagerMassage = new MessageManager();
    const ManagerMain = new MainManager();
}) 