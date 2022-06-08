var walletConnectProvider = null;

async function delay(ms) {
  return new Promise(resolve => setTimeout(() => resolve(1), ms));
}

function getProvider() {
  return walletConnectProvider;
}

async function connectWithWalletConnect() {
  window.localStorage.removeItem('walletconnect');
  window.localStorage.removeItem('WALLETCONNECT_DEEPLINK_CHOICE');
  if (walletConnectProvider && walletConnectProvider.connected) {
    await walletConnectProvider.disconnect();
    walletConnectProvider = null;
  }

  await delay(1500);

  return new Promise((resolve, reject) => {
    walletConnectProvider = new WalletConnectProvider.default({
      rpc: {
        1: 'https://rpc.ankr.com/eth',
        4: 'https://rpc.ankr.com/eth_rinkeby',
        44787: "https://alfajores-forno.celo-testnet.org",
        42220: "https://forno.celo.org",
      },
    });

    setTimeout(() => {
      if ((walletConnectProvider.isConnecting || walletConnectProvider.connected) && walletConnectProvider.accounts.length === 0) {
        showLoadingIcon(false);
        jQuery('#user-walletconnect-error').text('');
      }
    }, 10000);

    walletConnectProvider.enable().then(console.log).catch(console.error);

    walletConnectProvider.on("connect", () => {
      // alert('connected');
      showLoadingIcon(false);
      resolve(walletConnectProvider.accounts[0]);
    });

    walletConnectProvider.on("disconnect", (code, reason) => {
      console.log(code, reason);
      jQuery('#user_wallet_address').val('');
      jQuery('#user-walletconnect-error').text(reason);
      showLoadingIcon(false);
      setTimeout(() => jQuery('#user-walletconnect-error').text(''), 8000);
      reject(reason);
    });
  });
}

async function disconnectWalletConnect() {
  window.localStorage.removeItem('walletconnect');
  window.localStorage.removeItem('WALLETCONNECT_DEEPLINK_CHOICE');
  if (walletConnectProvider) {
    await walletConnectProvider.disconnect();
  }
}

async function signWithWalletConnect(address) {
  const message = walletconnection_wpScriptObject['signMessage'] + walletconnection_wpScriptObject['nonce'];
  const signature = await walletConnectProvider.request({
    method: 'personal_sign',
    params: [message, address],
  });
  return { address, signature };
}