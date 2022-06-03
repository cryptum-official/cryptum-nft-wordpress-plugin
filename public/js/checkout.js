
function showLoadingIcon(show = true) {
  jQuery('.loading-icon').css('display', show ? 'block' : 'none');
  jQuery('#user-wallet-connection-button').css('display', show ? 'none' : 'flex');
}
(function ($) {

  $('#user-wallet-connection-button').click(function (event) {
    event.preventDefault();
    $('#user_wallet_address').val('');

    showLoadingIcon();
    connectWithWalletConnect()
      .then(address => signWithWalletConnect(address))
      .then(({ address, signature }) => {
        console.log(address, signature);
        showLoadingIcon(false);
        $.ajax({
          method: 'POST',
          url: checkout_wpScriptObject.ajaxUrl,
          data: {
            security: checkout_wpScriptObject.security,
            action: checkout_wpScriptObject.action,
            address,
          },
          success: (data) => {
            $('#user_wallet_address').val(address);
          },
          error: (xhr, status, error) => {
            console.log(error);
            $('#user_wallet_address').val(address);
          },
        });
      }).catch(e => {
        console.error(e);
        // alert(e && e.message);
        showLoadingIcon(false);
      });
  });

  $('#user-wallet-generator-button').click(function (event) {
    event.preventDefault();

    const web3 = new Web3();
    const account = web3.eth.accounts.create();

    $('#user-wallet-modal-address').text(account.address);
    $('#user-wallet-modal-privateKey').text(account.privateKey);
    $('#user-wallet-generator-modal').dialog({
      modal: true,
      dialogClass: 'no-close',
      width: 500,
      buttons: {
        [checkout_wpScriptObject['save']]: function () {
          $.ajax({
            method: 'POST',
            url: checkout_wpScriptObject.ajaxUrl,
            data: {
              security: checkout_wpScriptObject.security,
              action: checkout_wpScriptObject.action,
              address: account.address,
            },
            success: (data) => {
              $('#user_wallet_address').val(account.address);
              $(this).dialog("close");
            },
            error: (xhr, status, error) => {
              console.log(error);
              $('#user-wallet-modal-error').text(error);
              $('#user-wallet-modal-error').css('display', 'block');
              $('#user_wallet_address').val(account.address);
              $(this).dialog("close");
            },
          });
        },
        [checkout_wpScriptObject['cancel']]: function () {
          $(this).dialog('close');
        }
      }
    });
  });
})(jQuery);
