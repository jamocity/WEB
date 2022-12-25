var compliment="congratulations for the ceremony today ,long life i wish you all";
var age=20;
 var repeat=setInterval( ()=>{
  console.log(compliment);
  age++;
  if (age==24) {
    clearInterval(repeat);
  }
}, 1000)

module.exports.age=age;
