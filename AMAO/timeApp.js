var greet =function (){
  console.log("hello world")

}
greet();

 function sendGreetings(salute){
   salute();
 }

 sendGreetings(greet);
