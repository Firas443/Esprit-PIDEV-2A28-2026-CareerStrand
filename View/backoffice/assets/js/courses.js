function validerCourse(){
    let isvalid= true
    var Title=document.getElementById("Title")
    var Titlevalue= Title.value
    removeMsg(Title)
    if (Titlevalue===""){
        showMsg(Title,"Please enter the title needed",false)
        isvalid=false
    } 
    else if(Titlevalue.length<3){
        showMsg(Title,"Title has to be longer than 3",false)
        isvalid=false

    }
    else{
        showMsg(Title,"Valid title",true)
        isvalid=true
    }

    var Description=document.getElementById("Description")
    var Descriptionvalue= Description.value
    removeMsg(Description)
    if (Descriptionvalue===""){
        showMsg(Description,"Please enter the description",false)
        isvalid=false
    } 
    else if(Descriptionvalue.length<15){
        showMsg(Description,"Description has to be longer than 15",false)
        isvalid=false

    }
    else{
        showMsg(Description,"Valid Description",true)
        isvalid=true
    }
    var Duration=document.getElementById("Duration")
    var Durationvalue= Duration.value
    removeMsg(Duration)
    if (Durationvalue===""){
        showMsg(Duration,"Please enter the duration",false)
        isvalid=false
    } 
    else if(Number(Durationvalue)<1){
        showMsg(Duration,"Duration has to be longer than 1",false)
        isvalid=false

    }
    else if(Number(Durationvalue)>4){
        showMsg(Duration,"Duration has to be under 4",false)
        isvalid=false

    }
    else{
        showMsg(Duration,"Valid Durattion",true)
        isvalid=true
    }
    const Published=document.querySelector("input[name='Published']")
    const Debut = Published.value;
    removeMsg(Published);
    if (!Debut) {
        showMsg(Published, "Please enter the date.", false);
        isvalid = false;
    } else {
        const today = new Date().toISOString();
        if(Debut >= today){
            showMsg(Published, "valid date ✓", true);
        }else {
            showMsg(Published, "Date not valid.", false);
            isvalid = false;
        }
    }
    return isvalid

}

function showMsg(input, message, success) {
    const msg = document.createElement("span");
    msg.className = "validation-msg " + (success ? "msg-success" : "msg-error");
    msg.textContent = message;
    input.insertAdjacentElement("afterend", msg);
}
function removeMsg(input) {
    const next = input.nextElementSibling;
    if (next && next.classList.contains("validation-msg")) {
        next.remove();
    }
}