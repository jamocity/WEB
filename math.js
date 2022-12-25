var adder= function (a,b) {
  return `the sum of the two numbers is ${a+b}`;


}
var subtractor= function (a,b) {
  return `the difference of the two numbers is ${a-b}`;


}
var divider= function (a,b) {
  return `the division of the two numbers is ${a/b}`;


}
var multiplier= function (a,b) {
  return `the product of the two numbers is ${a*b}`;


}
module.exports= {

  adder:adder,
  subtractor:subtractor,
  divider:divider,
  multiplier:multiplier
}
