var counter= function(arr){
  return 'there are ' + arr.length + '  element in the Array ';
};
var adder= function(a, b) {
  return `the sum of the two variable is ${a+b}`;


}
var pi=3.142;

//console.log(counter(['ade','ojo','ola']))
module.exports.counter= counter;
module.exports.adder= adder;
module.exports.pi= pi;
