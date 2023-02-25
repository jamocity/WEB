const fs = require('fs');

var reader=fs.readFile('readme.txt','utf8', (err, data)=>{
  console.log(data);
});
fs.writeFile('writeMe2.txt', reader);
