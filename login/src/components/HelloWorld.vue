<template>
  <div class="hello">
    <h1>Hello {{ name }}!!</h1>
    <h1>{{ msg }}</h1>
    <div>
    <ol><li v-for="(it,idx) in items" :key="idx">   <!--idxをキーとして繰り返している-->
    {{ it }} <button v-on:click="del_item( idx )"> x </button>   <!--デリートボタンにもキーが入っているのでどのitemを消すかを指定できる-->
    </li></ol>
    <input v-model="item" />
    <button v-on:click="add_item()">Add Item</button><!--v-on:clickでadd_Itemメソッドを呼び出す-->
    </div>
    <button class="signOut" @click="signOut">Sign out</button><!--@でv-onを省略してる、@clickでsignOutメソッドを呼び出す-->
  </div>
</template>

<script>
import firebase from 'firebase'

export default {
  data () {
    return {
      msg: 'Welcome to Your Vue.js App',  //ただのメッセージ
      name: firebase.auth().currentUser.email,  //Firebaseから取ってきたemail
      items: [ 'aaa', 'bbb', 'ccc' ],  //繰り返しで表示させるための配列（本来はDBから取ってきたデータを入れたい)
      item: 'Hello Vue.js!'   //inputのvalue。ここをコメントアウトしたが特にエラーはなかった。
    }
  },
  methods: {
    signOut: function () {  //firebaseのサインアウトのメソッド
      firebase.auth().signOut().then(() => {
        this.$router.push('/signin')
      })
    },
    add_item: function () { this.items.push(this.item) },   //itemsの後ろにitemを追加する。thisは付けないとあかんらしい。
    del_item: function (idx) { this.items.splice(idx, 1) }  //第一引数がidx、つまり消したいitemの位置。第二引数は消したい数。
  }
}

</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
h1, h2 {
  font-weight: normal;
}
ul {
  list-style-type: none;
  padding: 0;
}
li {
  display: block;
  margin: 10px 10px;
}
a {
  color: #42b983;
}
.signOut{
  margin-top: 20px;
}
</style>
