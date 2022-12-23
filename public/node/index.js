const Web3 = require('web3');
var web3 = new Web3('https://goerli.infura.io/v3/a2705a79f1da451ca9683d095bd5d87f');

// web3.eth.accounts.privateKeyToAccount('0x93f78d7bd7ef27829f4f2f06d77d057d05f1c5a32bade613a8a73088584795fc');
web3.eth.accounts.wallet.add('0x93f78d7bd7ef27829f4f2f06d77d057d05f1c5a32bade613a8a73088584795fc');
const account = web3.eth.accounts.wallet[0].address;
const arguments = process.argv.splice(2);
if(!arguments || arguments.length != 1){
    console.log("Parameter error!");
    return;
}
const message = arguments[0];//获取数组中的第一个参数
// const message = '0xe511f6c1331644505afe8fd5306cadff71d75d5c0f28d9df301b48900a008300';
const signature = web3.eth.sign(message, account);
console.log(signature);


